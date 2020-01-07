<?php

namespace FastLaravel\Http\Process;

use Swoole\Process;
use Swoole\Timer;
use FastLaravel\Http\Facade\Show;
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
     * @var null
     */
    protected $inotifyFd = null;

    /**
     * inotify reload lock
     *
     * @var int
     */
    protected $inotifyReloadLock = 0;

    /**
     * inotify file mask
     * @see https://www.php.net/manual/en/inotify.constants.php
     * @var int
     */
    protected $inotifyFileMask = null;

    /**
     * 支持的reload方式
     * @var array
     */
    protected $supportReloadTypes = [
        'tick',
        'inotify'
    ];

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
        $type = $this->getArg('hot_reload_type');
        if ($type && !in_array($type, $this->supportReloadTypes)) {
            Show::error("Hot reload type error.");
            $supportTypes = trim(implode('|', $this->supportReloadTypes), '|');
            Show::error("Only the following types ({$supportTypes}) are supported. '{$type}' you used.");
            $type = "";
        }
        if (extension_loaded('inotify') && (!$type || $type == 'inotify')) {
            $this->inotifyFileMask = IN_MODIFY | IN_CREATE | IN_IGNORED | IN_DELETE | IN_MOVE;
            $this->inotify();
            Show::info("starting hot reload: use inotify");
        } else {
            Timer::tick(2000, function () {
                $this->runComparison();
            });
            Show::info("starting hot reload: use timer tick comparison");
        }
    }

    /**
     * 定时器定时扫描文件变更
     */
    private function runComparison()
    {
        $startTime = microtime(true);
        $doReload = $reIndex = false;

        $files = ['dirs' => [], 'files' => []];
        $paths = $this->getArg('hot_reload_paths', [app_path()]);
        foreach ($paths as $path) {
            $val = File::scanDirectory($path);
            $files['dirs'] = array_merge($files['dirs'], $val['dirs']);
            $files['files'] = array_merge($files['files'], $val['files']);
        }
        if (isset($files['files']) && is_array($files['files'])) {
            $countFiles = count($files['files']);
            $count = count($this->index);

            // 非首次遍历,说明删除文件操作需要重建索引
            if ($countFiles < $count && !$this->firstScan) {
                $this->index = [];
                $reIndex = true;
                Show::error("Some files have been deleted.");
            }

            foreach ($files['files'] as $file) {
                if (! file_exists($file)) {
                    $doReload = true;
                    Show::writeln("File:<magenta>{$file}</magenta> has been deleted.");
                    continue;
                }
                $mTime = filemtime($file);
                $iNode = crc32($file);
                if (!isset($this->index[$iNode])) {
                    $doReload = true;
                    $this->index[$iNode] = ['m_time' => $mTime];
                    if (!$this->firstScan && !$reIndex) {
                        Show::writeln("File:<magenta>{$file}</magenta> has been added.");
                    }
                } elseif($this->index[$iNode]['m_time'] != $mTime) {
                    $doReload = true;
                    $this->index[$iNode] = ['m_time' => $mTime];
                    Show::writeln("File:<magenta>{$file}</magenta> has been modified.");
                }
            }
        }
        if ($doReload && !$this->firstScan) {
            $count = count($this->index);
            $time = date('Y-m-d H:i:s');
            $usage = round(microtime(true) - $startTime, 3);
            Show::writeln("Reload at <yellow>{$time}</yellow> use : <yellow>{$usage}</yellow> s include: <yellow>{$count}</yellow> files");
            $this->reloadServer();
        }
        $this->firstScan = false;
    }

    /**
     * inotify 扩展方式监控目录
     */
    public function inotify()
    {
        $wdConstants = array(
            1 => array('IN_ACCESS','File was accessed (read)'),
            2 => array('IN_MODIFY','File was modified'),
            4 => array('IN_ATTRIB','Metadata changed (e.g. permissions, mtime, etc.)'),
            8 => array('IN_CLOSE_WRITE','File opened for writing was closed'),
            16 => array('IN_CLOSE_NOWRITE','File not opened for writing was closed'),
            32 => array('IN_OPEN','File was opened'),
            128 => array('IN_MOVED_TO','File moved into watched directory'),
            64 => array('IN_MOVED_FROM','File moved out of watched directory'),
            256 => array('IN_CREATE','File or directory created in watched directory'),
            512 => array('IN_DELETE','File or directory deleted in watched directory'),
            1024 => array('IN_DELETE_SELF','Watched file or directory was deleted'),
            2048 => array('IN_MOVE_SELF','Watch file or directory was moved'),
            24 => array('IN_CLOSE','Equals to IN_CLOSE_WRITE | IN_CLOSE_NOWRITE'),
            192 => array('IN_MOVE','Equals to IN_MOVED_FROM | IN_MOVED_TO'),
            4095 => array('IN_ALL_EVENTS','Bitmask of all the above constants'),
            8192 => array('IN_UNMOUNT','File system containing watched object was unmounted'),
            16384 => array('IN_Q_OVERFLOW','Event queue overflowed (wd is -1 for this event)'),
            32768 => array('IN_IGNORED','Watch was removed (explicitly by inotify_rm_watch() or because file was removed or filesystem unmounted'),
            1073741824 => array('IN_ISDIR','Subject of this event is a directory'),
            1073741840 => array('IN_CLOSE_NOWRITE','High-bit: File not opened for writing was closed'),
            1073741856 => array('IN_OPEN','High-bit: File was opened'),
            1073742080 => array('IN_CREATE','High-bit: File or directory created in watched directory'),
            1073742336 => array('IN_DELETE','High-bit: File or directory deleted in watched directory'),
            16777216 => array('IN_ONLYDIR','Only watch pathname if it is a directory (Since Linux 2.6.15)'),
            33554432 => array('IN_DONT_FOLLOW','Do not dereference pathname if it is a symlink (Since Linux 2.6.15)'),
            536870912 => array('IN_MASK_ADD','Add events to watch mask for this pathname if it already exists (instead of replacing mask).'),
            2147483648 => array('IN_ONESHOT','Monitor pathname for one event, then remove from watch list.')
        );

        $this->inotifyFd = inotify_init();
        stream_set_blocking($this->inotifyFd, 0);
        $tempFiles = $monitorFiles = [];

        $paths = $this->getArg('hot_reload_paths', [app_path()]);
        foreach ($paths as $path) {
            $dirIterator = new \RecursiveDirectoryIterator($path);
            $iterator = new \RecursiveIteratorIterator($dirIterator);
            foreach ($iterator as $file) {
                $fileInfo = pathinfo($file);
                if (!isset($fileInfo['extension']) || $fileInfo['extension'] != 'php') {
                    continue;
                }
                //改为监听目录
                $dirPath = $fileInfo['dirname'];
                if (!isset($tempFiles[$dirPath])) {
                    $wd = inotify_add_watch($this->inotifyFd, $fileInfo['dirname'], $this->inotifyFileMask);
                    $tempFiles[$dirPath] = $wd;
                    $monitorFiles[$wd] = $dirPath;
                }
            }
        }
        unset($tempFiles);

        swoole_event_add($this->inotifyFd, function ($inotifyFd) use (&$monitorFiles, $wdConstants) {
            $events = inotify_read($inotifyFd);
            $flag = false;
            foreach ($events as $ev) {
                if (pathinfo($ev['name'], PATHINFO_EXTENSION) != 'php') {
                    // inotify 只能监听单层目录，所以子目录变化也需要加入监听
                    if ($ev['mask'] == 1073742080) {
                        $path = $monitorFiles[$ev['wd']] . '/' . $ev['name'];
                        $wd = inotify_add_watch($inotifyFd, $path, $this->inotifyFileMask);
                        $monitorFiles[$wd] = $path;
                    }
                    continue;
                } else {
                    $flag = true;
                    $msg = $wdConstants[$ev['mask']][1] ?? 'has been modified.';
                    Show::writeln('File:<magenta>' . $monitorFiles[$ev['wd']] . '/' . $ev['name'] . '</magenta> '. $msg . '.');
                }
            }
            if ($flag == true && !$this->inotifyReloadLock) {
                $this->inotifyReloadLock = Timer::after(1000, function () {
                    $this->reloadServer();
                    $this->inotifyReloadLock = 0;
                });
            }
        }, null, SWOOLE_EVENT_READ);
    }
}
