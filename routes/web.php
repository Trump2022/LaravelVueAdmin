<?php

use App\Http\Controllers\Api\UserAuthController;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

// 邮箱验证路由，限制每分钟最多10次请求
Route::get('/email/verify/{id}/{hash}', [UserAuthController::class, 'verifyEmail'])
    ->middleware(['signed', 'throttle:10,1'])->name('verification.verify');

$registerFrontendRoutes = static function (callable $frontend): void {
    Route::get('/', $frontend);
    Route::get('/web/{path?}', $frontend)->where('path', '.*');
    Route::get('/admin/{path?}', $frontend)->where('path', '.*');
};

if (app()->environment('local')) {
    // php artisan serve + npm run dev(或build)
    // 本地开发环境下，返回 vue-learning 视图作为前端入口
    $frontend = static fn () => view('vue-learning');
    $registerFrontendRoutes($frontend);

    // 处理所有未匹配的路由，返回 vue-learning 视图
    Route::fallback($frontend);
} else {
    // 生产环境下，根据 .env中的 FRONTEND_OUT_DIR 的值决定前端入口是 Laravel Blade 还是 Vue SPA
    $frontendOutDir = trim((string) config('frontend.out_dir', ''));

    if ($frontendOutDir === '') {
        // FRONTEND_OUT_DIR 为空：生产环境由 Laravel Blade 提供入口
        $frontend = static fn () => view('vue-learning');
    } else {
        // FRONTEND_OUT_DIR 有值：由 Laravel 返回独立 SPA 的入口文件
        $indexPath = base_path(trim($frontendOutDir, '/') . '/index.html');
        $frontend = static fn () => response()->file($indexPath);
    }

    $registerFrontendRoutes($frontend);

    // 其他普通 GET 路径回退到 SPA  由 Vue Router 处理未知前端路径 或 回退到 Laravel Blade入口。
    // API、Sanctum、健康检查和存储路径留给后端或 Nginx，避免把它们的 404 变成默认首页。
    Route::get('/{path}', $frontend)->where('path', '^(?!(?:api|sanctum|up|storage)(?:/|$)).*');
}
