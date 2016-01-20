<?php

namespace Toplan\Sms;

use DB;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\ServiceProvider;
use Toplan\PhpSms\Sms;

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

        // init phpsms
        $this->initPhpSms();

        // store to container
        $this->app->singleton('SmsManager', function () {
            return new SmsManager();
        });
    }

    /**
     * bootstrap PhpSms
     */
    protected function initPhpSms()
    {
        //export custom rule flag value
        define('CUSTOM_RULE', SmsManager::CUSTOM_RULE_FLAG);

        // define how to use queue
        $queueJob = config('laravel-sms.queueJob', 'App\Jobs\SendReminderSms');
        Sms::queue(function ($sms, $data) use ($queueJob) {
            if (!class_exists($queueJob)) {
                throw new LaravelSmsException("Class [$queueJob] does not exists.");
            }
            $this->dispatch(new $queueJob($sms));

            return [
                'success'             => true,
                'after_push_to_queue' => true,
            ];
        });

        // before send hook
        // store sms data to database
        Sms::beforeSend(function ($task) {
            if (!config('laravel-sms.database_enable', false)) {
                return true;
            }
            $data = $task->data ?: [];
            $id = DB::table('laravel_sms')->insertGetId([
                'to'         => $data['to'],
                'temp_id'    => json_encode($data['templates']),
                'data'       => json_encode($data['templateData']),
                'content'    => $data['content'] ?: '',
                'voice_code' => $data['voiceCode'] ?: '',
                'created_at' => date('Y-m-d H:i:s', time()),
            ]);
            $data['smsId'] = $id;
            $task->data($data);
        });

        // after send hook
        // update sms data in database
        Sms::afterSend(function ($task, $result) {
            if (!config('laravel-sms.database_enable', false)) {
                return true;
            }

            // get time
            $microTime = $result['time']['finished_at'];
            $finishedAt = explode(' ', $microTime)[1];

            // get sms id
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
