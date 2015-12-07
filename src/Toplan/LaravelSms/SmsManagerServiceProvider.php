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
        // merge configs
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/laravel-sms.php', 'laravel-sms'
        );

        // init phpsms
        $this->initPhpSms();

        // store to container
        $this->app->singleton('SmsManager', function(){
            return new SmsManager();
        });
    }

    /**
     * bootstrap PhpSms
     */
    protected function initPhpSms()
    {
        // define how to use queue
        Sms::queue(function($sms, $data){
            $this->dispatch(new SendReminderSms($sms));
            return [
                'success' => true,
                'after_push_to_queue' => true,
            ];
        });

        // before send hook
        // store sms data to database
        Sms::beforeSend(function($task){
            $data = $task->data ?: [];
            $id = DB::table('sms')->insertGetId([
                'to' => $data['to'],
                'temp_id' => json_encode($data['templates']),
                'data' => json_encode($data['templateData']),
                'content' => $data['content'],
                'created_at' => date('Y-m-d H:i:s', time())
            ]);
            $data['smsId'] = $id;
            $task->data($data);
        });

        // after send hook
        // update sms data in database
        Sms::afterSend(function($task, $results){
            $success = false;
            $finishedAt = 0;

            // parse status from results data
            $lastRecord = array_pop($results);
            if ($lastRecord) {
                $success = $lastRecord['success'];
                $microTime = $lastRecord['time']['finished_at'];
                $finishedAt = explode(' ', $microTime)[1];
                array_push($results, $lastRecord);
            }

            // get sms id
            $data = $task->data;
            $smsId = isset($data['smsId']) ? $data['smsId'] : 0;

            // update database
            DB::beginTransaction();
            $dbData = [];
            $dbData['updated_at'] = date('Y-m-d H:i:s', $finishedAt);
            $dbData['result_info'] = json_encode($results);
            if ($success) {
                $dbData['sent_time'] = $finishedAt;
            } else {
                DB::table('sms')->where('id', $smsId)->increment('fail_times');
                $dbData['last_fail_time'] = $finishedAt;
            }
            DB::table('sms')->where('id', $smsId)->update($dbData);
            DB::commit();
            return [
                'success' => $success
            ];
        });
    }

    public function provides()
    {
        return array('SmsManager');
    }
}