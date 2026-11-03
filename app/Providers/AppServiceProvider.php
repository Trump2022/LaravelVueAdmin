<?php

namespace App\Providers;

use App\Models\Admin;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(static function (CanResetPassword $user, string $token): string {
            $query = [
                'email' => $user->getEmailForPasswordReset(),
            ];

            if ($user instanceof Admin) {
                $path = '/admin/reset-password';
                $query['token'] = $token;
            } else {
                $path = '/web/account';
                $query['reset_token'] = $token;
            }

            return rtrim(config('app.url'), '/').$path.'?'.http_build_query($query);
        });
    }
}
