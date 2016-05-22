<?php

namespace Toplan\Sms;

use DB;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\ServiceProvider;
use PhpSms;

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
            __DIR__ . '/../../config/laravel-sms.php' => config_path('laravel-sms.php'),
        ], 'config');

        //publish migrations
        $this->publishes([
            __DIR__ . '/../../../migrations/' => database_path('/migrations'),
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

        // initialize the PhpSms
        $this->initPhpSms();

        // store to container
        $this->app->singleton('SmsManager', function ($app) {
            $token = $app->request->header('access-token', null);
            if (empty($token)) {
                $token = $app->request->input('access_token', null);
            }

            return new SmsManager($token);
        });
    }

    /**
     * Initialize the PhpSms
     */
    protected function initPhpSms()
    {
        // define how to pushed to the queue system
        $queueJob = config('laravel-sms.queueJob', 'Toplan\Sms\SendReminderSms');
        PhpSms::queue(false, function ($sms) use ($queueJob) {
            if (!class_exists($queueJob)) {
                throw new LaravelSmsException("Class [$queueJob] does not exists.");
            }
            $this->dispatch(new $queueJob($sms));
        });

        // store sms data into the database before sending
        PhpSms::beforeSend(function ($task) {
            if (!config('laravel-sms.dbLogs', false)) {
                return true;
            }
            $data = $task->data ?: [];
            $id = DB::table('laravel_sms')->insertGetId([
                'to'         => $data['to'] ?: '',
                'temp_id'    => json_encode($data['templates']),
                'data'       => json_encode($data['templateData']),
                'content'    => $data['content'] ?: '',
                'voice_code' => $data['voiceCode'] ?: '',
                'created_at' => date('Y-m-d H:i:s', time()),
            ]);
            $data['smsId'] = $id;
            $task->data($data);
        });

        // update sms data in the database after sending
        PhpSms::afterSend(function ($task, $result) {
            if (!config('laravel-sms.dbLogs', false)) {
                return true;
            }
            $microTime = $result['time']['finished_at'];
            $finishedAt = explode(' ', $microTime)[1];
            $data = $task->data;
            $smsId = isset($data['smsId']) ? $data['smsId'] : 0;
            // update database
            DB::beginTransaction();
            $dbData = [];
            $dbData['updated_at'] = date('Y-m-d H:i:s', $finishedAt);
            $dbData['result_info'] = json_encode($result['logs']);
            if ($result['success']) {
                $dbData['sent_time'] = $finishedAt;
            } else {
                DB::table('laravel_sms')->where('id', $smsId)->increment('fail_times');
                $dbData['last_fail_time'] = $finishedAt;
            }
            DB::table('laravel_sms')->where('id', $smsId)->update($dbData);
            DB::commit();
        });
    }

    public function provides()
    {
        return array('SmsManager');
    }
}
