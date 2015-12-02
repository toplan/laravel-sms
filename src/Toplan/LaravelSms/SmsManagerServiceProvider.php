<?php
namespace Toplan\Sms;

use App\Jobs\SendReminderSms;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\ServiceProvider;
use Toplan\PhpSms\Sms;
use DB;

class SmsManagerServiceProvider extends ServiceProvider
{
    use DispatchesJobs;

    /**
     * bootstrap, add routes
     */
    public function boot()
    {
        //publish a config file
        $this->publishes([
            __DIR__ . '/../../config/laravel-sms.php' => config_path('laravel-sms.php')
        ], 'config');

        //publish migrations
        $this->publishes([
            __DIR__ . '/../../../migrations/' => database_path('/migrations')
        ], 'migrations');

        //route file
        require __DIR__ . '/routes.php';

        //validations file
        require __DIR__ . '/validations.php';
    }

    /**
     * register the service provider
     */
    public function register()
    {
        // Merge configs
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/laravel-sms.php', 'laravel-sms'
        );

        Sms::queue(function($sms, $data){
           return $this->dispatch(new SendReminderSms($sms));
        });

        Sms::beforeSend(function($task){
            $data = $task->data;
            $id = DB::table('sms')->insertGetId([
                'to' => $data['to'],
                'temp_id' => json_encode($data['templates']),
                'data' => json_encode($data['templateData']),
                'content' => $data['content'],
            ]);
            $data['smsId'] = $id;
            $task->data($data);
        });

        Sms::afterSend(function($task, $results){
            $data = $task->data;
            $smsId = isset($data['smsId']) ? $data['smsId'] : 0;
            DB::table('sms')->where('id', $smsId)->update([
                'result_info' => json_encode($results),
            ]);
        });

        $this->app->singleton('SmsManager', function(){
            return new SmsManager($this->app);
        });
    }

    public function provides()
    {
        return array('SmsManager');
    }
}