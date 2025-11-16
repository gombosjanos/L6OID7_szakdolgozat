<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            $base = rtrim(config('app.frontend_url'), '/');
            $email = urlencode($notifiable->email ?? '');
            return sprintf('%s/reset-password?token=%s&email=%s', $base, $token, $email);
        });
    }
}
