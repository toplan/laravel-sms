<?php namespace Toplan\Sms;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class SmsManagerServiceProvider extends ServiceProvider{

    /**
     * bootstrap, add routes
     */
    public function boot()
    {
        $this->package('toplan/laravel-sms');
        require __DIR__ . '/routes.php';
        require __DIR__ . '/validations.php';
    }

    /**
     * register the service provider
     */
    public function register()
    {
        $this->app->singleton('SmsManager', function(){
            return new SmsManager($this->app);
        });
    }

    public function provides()
    {
        return array('SmsManager');
    }
}