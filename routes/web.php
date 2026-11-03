<?php

use App\Http\Controllers\Api\UserAuthController;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

// 邮箱验证路由，限制每分钟最多10次请求
Route::get('/email/verify/{id}/{hash}', [UserAuthController::class, 'verifyEmail'])
    ->middleware(['signed', 'throttle:10,1'])->name('verification.verify');

if (app()->environment('local')) {
    // php artisan serve + npm run dev
    // 本地开发环境下，返回 vue-learning 视图给前端 Vue SPA 处理路由

    Route::view('/', 'vue-learning');
    Route::view('/web/{path?}', 'vue-learning')->where('path', '.*');
    Route::view('/admin/{path?}', 'vue-learning')->where('path', '.*');
    Route::fallback(fn () => view('vue-learning')); // 处理所有未匹配的路由，返回 vue-learning 视图
} else {
    // php-fpm + nginx
    // 生产环境下，返回 dist/index.html 文件给前端 Vue SPA 处理路由

    $frontend = fn () => response()->file(base_path('dist/index.html'));

    // 明确交给 Vue SPA 处理的前端路由。
    Route::get('/', $frontend);
    Route::get('/web/{path?}', $frontend)->where('path', '.*');
    Route::get('/admin/{path?}', $frontend)->where('path', '.*');

    // 其他普通 GET 路径回退到 SPA，由 Vue Router 处理未知前端路径。
    // API、Sanctum、健康检查和存储路径留给后端或 Nginx，避免把它们的 404 变成前端页面。
    Route::get('/{path}', $frontend)->where('path', '^(?!(?:api|sanctum|up|storage)(?:/|$)).*');
}
