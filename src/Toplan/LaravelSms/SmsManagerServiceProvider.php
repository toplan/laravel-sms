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

        if (config('laravel-sms.route.enable', true)) {
            require __DIR__ . '/routes.php';
        }

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
            $to = is_array($data['to']) ? json_encode($data['to']) : $data['to'];
            $id = DB::table('laravel_sms')->insertGetId([
                'to'         => $to ?: '',
                'temp_id'    => json_encode($data['templates']),
                'data'       => json_encode($data['data']),
                'content'    => $data['content'] ?: '',
                'voice_code' => $data['code'] ?: '',
                'created_at' => date('Y-m-d H:i:s', time()),
            ]);
            $data['_sms_id'] = $id;
            $task->data($data);
        });

        PhpSms::afterSend(function ($task, $result) {
            if (!config('laravel-sms.dbLogs', false)) {
                return true;
            }
            $microTime = $result['time']['finished_at'];
            $finishedAt = explode(' ', $microTime)[1];
            $data = $task->data;
            if (!isset($data['_sms_id'])) {
                return true;
            }

            DB::beginTransaction();
            $dbData = [];
            $dbData['updated_at'] = date('Y-m-d H:i:s', $finishedAt);
            $dbData['result_info'] = json_encode($result['logs']);
            if ($result['success']) {
                $dbData['sent_time'] = $finishedAt;
            } else {
                DB::table('laravel_sms')->where('id', $data['_sms_id'])->increment('fail_times');
                $dbData['last_fail_time'] = $finishedAt;
            }
            DB::table('laravel_sms')->where('id', $data['_sms_id'])->update($dbData);
            DB::commit();
        });
    }
}
