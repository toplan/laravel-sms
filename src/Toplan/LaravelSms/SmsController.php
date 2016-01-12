<?php

namespace Toplan\Sms;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SmsManager;

class SmsController extends Controller
{
    protected $phpSms;

    public function __construct()
    {
        $this->phpSms = app('PhpSms');
    }

    private function parseInput($request)
    {
        $mobile = $request->input('mobile', null);
        $rule = $request->input('mobileRule', null);
        $uuid = $request->input('uuid', null);
        $seconds = $request->input('seconds', 60);

        return compact('mobile', 'rule', 'uuid', 'seconds');
    }

    public function postVoiceVerify(Request $request)
    {
        //get data
        extract($input = $this->parseInput($request));

        //validate
        $verifyResult = SmsManager::validator($input);
        if (!$verifyResult['success']) {
            return response()->json($verifyResult);
        }

        //request voice verify
        $code = SmsManager::generateCode();
        $result = $this->phpSms->voice($code)->to($mobile)->send();
        if ($result['success']) {
            $data = SmsManager::getSentInfo();
            $data['sent'] = true;
            $data['mobile'] = $mobile;
            $data['code'] = $code;
            $data['deadline_time'] = time() + (15 * 60);
            SmsManager::storeSentInfo($uuid, $data);
            SmsManager::storeCanSendTime($uuid, $seconds);
            $verifyResult = SmsManager::genResult(true, 'voice_send_success');
        } else {
            $verifyResult = SmsManager::genResult(false, 'voice_send_failure');
        }

        return response()->json($verifyResult);
    }

    public function postSendCode(Request $request)
    {
        //get data
        extract($input = $this->parseInput($request));

        //validate
        $verifyResult = SmsManager::validator($input);
        if (!$verifyResult['success']) {
            return response()->json($verifyResult);
        }

        //send verify sms
        $code = SmsManager::generateCode();
        $minutes = SmsManager::getCodeValidTime();
        $templates = SmsManager::getVerifySmsTemplates();
        $template = SmsManager::getVerifySmsContent();
        try {
            $content = vsprintf($template, [$code, $minutes]);
        } catch (\Exception $e) {
            $content = $template;
        }
        $result = $this->phpSms->make($templates)->to($mobile)
                         ->data(['code' => $code, 'minutes' => $minutes])
                         ->content($content)->send();
        if ($result['success']) {
            $data = SmsManager::getSentInfo();
            $data['sent'] = true;
            $data['mobile'] = $mobile;
            $data['code'] = $code;
            $data['deadline_time'] = time() + ($minutes * 60);
            SmsManager::storeSentInfo($uuid, $data);
            SmsManager::storeCanSendTime($uuid, $seconds);
            $verifyResult = SmsManager::genResult(true, 'sms_send_success');
        } else {
            $verifyResult = SmsManager::genResult(false, 'sms_send_failure');
        }

        return response()->json($verifyResult);
    }

    public function getInfo(Request $request, $uuid = null)
    {
        $html = '<meta charset="UTF-8"/><h2 align="center" style="margin-top: 20px;">Hello, welcome to laravel-sms v2.0</h2>';
        $html .= '<p style="color: #666;"><a href="https://github.com/toplan/laravel-sms" target="_blank">laravel-sms源码</a>托管在GitHub，欢迎你的使用。如有问题和建议，欢迎提供issue。当然你也能为该项目提供开源代码，让laravel-sms支持更多服务商。</p>';
        $html .= '<hr>';
        $html .= '<p>你可以在调试模式(设置config/app.php中的debug为true)下查看到存储在session中的验证码短信相关数据(方便你进行调试)：</p>';
        echo $html;
        $uuid = $uuid ?: $request->input('uuid', null);
        if (config('app.debug')) {
            $smsData = SmsManager::retrieveDebugInfo($uuid);
            dd($smsData);
        } else {
            echo '<p align="center" style="color: #ff0000;;">现在是非调试模式，无法查看验证码短信数据</p>';
        }
    }
}
