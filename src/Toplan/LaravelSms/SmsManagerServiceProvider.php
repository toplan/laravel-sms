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
     * 启动服务
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../../config/laravel-sms.php' => config_path('laravel-sms.php'),
        ], 'config');

        $this->publishes([
            __DIR__ . '/../../../migrations/' => database_path('/migrations'),
        ], 'migrations');

        require __DIR__ . '/routes.php';

        require __DIR__ . '/validations.php';

        $this->phpSms();
    }

    /**
     * 注册服务
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/laravel-sms.php', 'laravel-sms');

        $this->app->singleton('Toplan\\Sms\\SmsManager', function ($app) {
            $token = $app->request->header('access-token', null);
            if (empty($token)) {
                $token = $app->request->input('access_token', null);
            }
            $input = $app->request->all();

            return new SmsManager($token, $input);
        });
    }

    /**
     * 配置PhpSms
     */
    protected function phpSms()
    {
        $queueJob = config('laravel-sms.queueJob', 'Toplan\Sms\SendReminderSms');
        PhpSms::queue(false, function ($sms) use ($queueJob) {
            if (!class_exists($queueJob)) {
                throw new LaravelSmsException("Class [$queueJob] does not exists.");
            }
            $this->dispatch(new $queueJob($sms));
        });

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

        PhpSms::afterSend(function ($task, $result) {
            if (!config('laravel-sms.dbLogs', false)) {
                return true;
            }
            $microTime = $result['time']['finished_at'];
            $finishedAt = explode(' ', $microTime)[1];
            $data = $task->data;
            $smsId = isset($data['smsId']) ? $data['smsId'] : 0;

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
}
