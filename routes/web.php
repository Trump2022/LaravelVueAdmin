<?php

use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

if (app()->environment('local')) {

    Route::view('/', 'vue-learning');
    Route::view('/web/{path?}', 'vue-learning')->where('path', '.*');
    Route::view('/admin/{path?}', 'vue-learning')->where('path', '.*');
    // Route::view('/{path}', 'vue-learning')->where('path', '.*');//(如果没有其它规则匹配)任意路径，返回 vue-learning 视图
    // Route::fallback(function () {
    //     return view('vue-learning');
    // });
    Route::fallback(fn() => view('vue-learning'));// 处理所有未匹配的路由，返回 vue-learning 视图, 与上面注释掉的闭包写法完全等同
} else {
    $frontend = fn () => response()->file(base_path('dist/index.html'));

    // 明确交给 Vue SPA 处理的前端路由。
    Route::get('/', $frontend);
    Route::get('/web/{path?}', $frontend)->where('path', '.*');
    Route::get('/admin/{path?}', $frontend)->where('path', '.*');

    // 其他普通 GET 路径回退到 SPA，由 Vue Router 处理未知前端路径。
    // API、Sanctum、健康检查和存储路径留给后端或 Nginx，避免把它们的 404 变成前端页面。
    Route::get('/{path}', $frontend) ->where('path', '^(?!(?:api|sanctum|up|storage)(?:/|$)).*');
}

