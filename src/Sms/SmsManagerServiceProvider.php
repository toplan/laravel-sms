<?php namespace Toplan\Sms;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class SmsManagerServiceProvider extends ServiceProvider{

    /**
     * bootstrap, add routes
     */
    public function boot()
    {
        $this->package('toplan/sms', null, __DIR__);
        require __DIR__ . '/routes.php';
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