<?php

namespace FastLaravel\Http\Process;

use Swoole\Process;
use Swoole\Timer;
use FastLaravel\Http\Util\File;

/**
 * 热加载文件（单独使用无需fswatch）
 *
 * Class HotReload
 *
 * @package App\Process
 */
class HotReload extends BaseProcess
{
    /**
     * 索引
     *
     * @var
     */
    protected $index = [];

    /**
     * 首次扫描
     *
     * @var
     */
    protected $firstScan = true;

    /**
     * 启动定时器进行循环扫描
     *
     * @param Process $process
     *
     * @return null
     */
    public function run(Process $process)
    {
        // 扩展可用 优先使用扩展进行处理,不可用通过tick定时检查方式
        sleep(1);
        if (extension_loaded('inotify')) {
            $this->inotify();
            output()->writeln("starting hot reload: use inotify");
        } else {
            Timer::tick(2000, function () {
                $this->runComparison();
            });
            output()->writeln("starting hot reload: use timer tick comparison");
        }
    }

    /**
     * 扫描文件变更
     */
    private function runComparison()
    {
        $startTime = microtime(true);
        $doReload = $reIndex = false;
        $files = File::scanDirectory(app_path());
        if (isset($files['files']) && is_array($files['files'])) {
            $countFiles = count($files['files']);
            $count = count($this->index);

            // 非首次遍历,说明删除文件操作需要重建索引
            if ($countFiles < $count && !$this->firstScan) {
                $this->index = [];
                $reIndex = true;
                output()->writeln("<red>Some files have been deleted.</red>");
            }

            foreach ($files['files'] as $file) {
                if (! file_exists($file)) {
                    $doReload = true;
                    output()->writeln("File:<magenta>{$file}</magenta> has been deleted.");
                    continue;
                }
                $mTime = filemtime($file);
                $iNode = crc32($file);
                if (!isset($this->index[$iNode])) {
                    $doReload = true;
                    $this->index[$iNode] = ['m_time' => $mTime];
                    if (!$this->firstScan && !$reIndex) {
                        output()->writeln("File:<magenta>{$file}</magenta> has been added.");
                    }
                } elseif($this->index[$iNode]['m_time'] != $mTime) {
                    $doReload = true;
                    $this->index[$iNode] = ['m_time' => $mTime];
                    output()->writeln("File:<magenta>{$file}</magenta> has been modified.");
                }
            }
        }
        if ($doReload && !$this->firstScan) {
            $count = count($this->index);
            $time = date('Y-m-d H:i:s');
            $usage = round(microtime(true) - $startTime, 3);
            output()->writeln("Reload at <yellow>{$time}</yellow> use : <yellow>{$usage}</yellow> s include: <yellow>{$count}</yellow> files");
            $this->reloadServer();
        }
        $this->firstScan = false;
    }

    /**
     * 监控目录
     */
    public function inotify()
    {
        $this->inotifyFd = inotify_init();

        stream_set_blocking($this->inotifyFd, 0);
        $dirIterator = new \RecursiveDirectoryIterator(app_path());
        $iterator = new \RecursiveIteratorIterator($dirIterator);
        $monitorFiles = [];
        $tempFiles = [];

        foreach ($iterator as $file) {
            $fileInfo = pathinfo($file);

            if (!isset($fileInfo['extension']) || $fileInfo['extension'] != 'php') {
                continue;
            }

            //改为监听目录
            $dirPath = $fileInfo['dirname'];
            if (!isset($tempFiles[$dirPath])) {
                $wd = inotify_add_watch($this->inotifyFd, $fileInfo['dirname'], IN_MODIFY | IN_CREATE | IN_IGNORED | IN_DELETE);
                $tempFiles[$dirPath] = $wd;
                $monitorFiles[$wd] = $dirPath;
            }
        }

        $tempFiles = null;

        swoole_event_add($this->inotifyFd, function ($inotifyFd) use (&$monitorFiles) {
            $events = inotify_read($inotifyFd);
            $flag = true;
            foreach ($events as $ev) {
                if (pathinfo($ev['name'], PATHINFO_EXTENSION) != 'php') {
                    //创建目录添加监听
                    if ($ev['mask'] == 1073742080) {
                        $path = $monitorFiles[$ev['wd']] . '/' . $ev['name'];

                        $wd = inotify_add_watch($inotifyFd, $path, IN_MODIFY | IN_CREATE | IN_IGNORED | IN_DELETE);
                        $monitorFiles[$wd] = $path;
                    }
                    $flag = false;
                    continue;
                }
                output()->writeln('File:<magenta>' . $monitorFiles[$ev['wd']] . '/' . $ev['name'] . '</magenta> has been modified.');
            }
            if ($flag == true) {
                $this->reloadServer();
            }
        }, null, SWOOLE_EVENT_READ);
    }

    public function onShutDown()
    {
        // TODO: Implement onShutDown() method.
    }

    public function onReceive(string $str)
    {
        // TODO: Implement onReceive() method.
    }
}
