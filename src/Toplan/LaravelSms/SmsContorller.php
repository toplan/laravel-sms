<?php
namespace Toplan\Sms;

use \SmsManager;
use \Input;
use Illuminate\Routing\Controller;

class SmsController extends Controller
{
    protected $phpSms;

    public function __construct()
    {
        $this->phpSms = app('PhpSms');
    }

    public function postVoiceVerify($mobile = '', $rule = '')
    {
        $uuid = Input::get('uuid', null);
        $input = [
            'mobile' => $mobile,
            'seconds' => Input::get('seconds', 60),
        ];
        $verifyResult = SmsManager::validator($input, $rule);
        if (!$verifyResult['success']) {
            return response()->json($verifyResult);
        }
        $code = SmsManager::generateCode();
        $model = $this->phpSms;
        $result = $model::voice($code)->to($mobile)->send();
        if ($result) {
            $data = SmsManager::getSentInfo();
            $data['sent'] = true;
            $data['mobile'] = $mobile;
            $data['code'] = $code;
            $data['deadline_time'] = time() + (15 * 60);
            SmsManager::storeSentInfo($uuid, $data);
            SmsManager::storeCanSendTime($uuid, $input['seconds']);
        } else {
            $verifyResult['success'] = false;
            $verifyResult['type'] = 'request_failed';
        }
        return response()->json($verifyResult);
    }

    public function postSendCode($mobile = '', $rule = '')
    {
        $uuid = Input::get('uuid', null);
        $seconds = Input::get('seconds', 60);
        $verifyResult = SmsManager::validator([
            'mobile' => $mobile,
            'seconds' => $seconds,
        ], $rule);
        if (!$verifyResult['success']) {
            return response()->json($verifyResult);
        }
        $code     = SmsManager::generateCode();
        $minutes  = SmsManager::getCodeValidTime();
        $templates = SmsManager::getVerifySmsTemplates();
        $template = SmsManager::getVerifySmsContent();
        try {
            $content  = vsprintf($template, [$code, $minutes]);
        } catch (\Exception $e) {
            $content = $template;
        }
        $result = $this->phpSms->make($templates)
                         ->to($mobile)
                         ->data(['code' => $code,'minutes' => $minutes])
                         ->content($content)
                         ->send();
        if ($result) {
            $data = SmsManager::getSentInfo();
            $data['sent'] = true;
            $data['mobile'] = $mobile;
            $data['code'] = $code;
            $data['deadline_time'] = time() + ($minutes * 60);
            SmsManager::storeSentInfo($uuid, $data);
            SmsManager::storeCanSendTime($uuid, $seconds);
        } else {
            $verifyResult['success'] = false;
            $verifyResult['type'] = 'request_failed';
        }
        return response()->json($verifyResult);
    }

    public function getInfo()
    {
        $html = '<meta charset="UTF-8"/><h2 align="center" style="margin-top: 20px;">Hello, welcome to laravel-sms for l5.</h2>';
        $html .= '<p style="color: #666;"><a href="https://github.com/toplan/laravel-sms" target="_blank">laravel-sms源码</a>托管在GitHub，欢迎你的使用。如有问题和建议，欢迎提供issue。当然你也能为该项目提供开源代码，让laravel-sms支持更多服务商。</p>';
        $html .= '<hr>';
        $html .= '<p>你可以在调试模式(设置config/app.php中的debug为true)下查看到存储在session中的验证码短信相关数据(方便你进行调试)：</p>';
        echo $html;
        if (config('app.debug')) {
            dd(SmsManager::getSmsDataFromSession());
        } else {
            echo '<p align="center" style="color: #ff0000;;">现在是非调试模式，无法查看验证码短信数据</p>';
        }
    }
}
