<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;

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
        ResetPassword::createUrlUsing(
            static fn (User $user, string $token): string =>
                rtrim(config('app.url'), '/').'/web/account?'.http_build_query([
                    'reset_token' => $token,
                    'email' => $user->getEmailForPasswordReset(),
                ]),
        );
    }
}
