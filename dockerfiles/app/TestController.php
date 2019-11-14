<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

class TestController extends Controller
{

    /**
     * 测试控制器
     */
    public function info()
    {
        return response()->json([
            'data'       => 'hello world',
            'route_name' => request()->route()->getName()
        ]);
    }

}
