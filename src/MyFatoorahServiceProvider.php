<?php

namespace MarwaMourad15\LaravelPaymentMyfatoorah;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class MyFatoorahServiceProvider extends ServiceProvider {

    /**
     * Register services.
     *
     * @return void
     */
    public function register() {
        $this->publishes([
            __DIR__ . '/config/myfatoorah.php' => config_path('myfatoorah.php'),
                ], 'myfatoorah');

        $this->publishes([
            __DIR__ . '/controller/MyFatoorahController.php' => app_path() . '/Http/Controllers/MyFatoorahController.php',
                ], 'myfatoorah');

        Route::get('myfatoorah', [
            'as'   => 'myfatoorah', 'uses' => \App\Http\Controllers\Api\MyFatoorahController::class . '@index'
        ]);
        Route::get('myfatoorah/callback', [
            'as'   => 'myfatoorah.callback', 'uses' => \App\Http\Controllers\Api\MyFatoorahController::class . '@callback'
        ]);

        defined('MYFATOORAH_LARAVEL_PACKAGE_VERSION') or define('MYFATOORAH_LARAVEL_PACKAGE_VERSION', '2.1.0');
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot() {
        $this->mergeConfigFrom(
                __DIR__ . '/config/myfatoorah.php', 'myfatoorah'
        );
    }

}
