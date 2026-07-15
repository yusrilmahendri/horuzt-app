<?php

namespace App\Providers;

use App\Contracts\GlobalMusicCatalogProvider;
use App\Contracts\GlobalMusicProviderInterface;
use App\Contracts\WhatsAppGateway;
use App\Services\GlobalCatalog\NullGlobalMusicCatalogProvider;
use App\Services\HttpWhatsAppGateway;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Midtrans\Config;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $resolver = function ($app) {
            $providerKey = (string) config('music_catalog.provider', 'null');
            $providers = (array) config('music_catalog.providers', []);
            $providerClass = $providers[$providerKey] ?? config('music_catalog.provider_class', NullGlobalMusicCatalogProvider::class);

            if (! class_exists($providerClass)) {
                $providerClass = NullGlobalMusicCatalogProvider::class;
            }

            $provider = $app->make($providerClass);

            if (! $provider instanceof GlobalMusicProviderInterface) {
                return $app->make(NullGlobalMusicCatalogProvider::class);
            }

            return $provider;
        };

        $this->app->bind(GlobalMusicProviderInterface::class, $resolver);
        $this->app->bind(GlobalMusicCatalogProvider::class, $resolver);
        $this->app->bind(WhatsAppGateway::class, HttpWhatsAppGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = filter_var(config('midtrans.is_production'), FILTER_VALIDATE_BOOLEAN);
        Config::$isSanitized = filter_var(config('midtrans.is_sanitized'), FILTER_VALIDATE_BOOLEAN);
        Config::$is3ds = filter_var(config('midtrans.is_3ds'), FILTER_VALIDATE_BOOLEAN);

        // Point the password reset link in emails to the Angular frontend.
        ResetPassword::createUrlUsing(function ($user, string $token) {
            return rtrim((string) config('verification.frontend_url'), '/')
                .'/reset-password?token='.$token
                .'&email='.urlencode($user->getEmailForPasswordReset());
        });
    }
}
