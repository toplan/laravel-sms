<?php

namespace Toplan\Sms;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PhpSms as Sms;
use SmsManager as Manager;

class SmsController extends Controller
{
    public function postVoiceVerify(Request $request)
    {
        $verifyResult = Manager::validate($request->all());
        if (!$verifyResult['success']) {
            return response()->json($verifyResult);
        }

        $mobile = $request->input('mobile', null);
        $token = $request->input('token', null);
        $seconds = $request->input('seconds', 60);

        $code = Manager::generateCode();
        $templates = Manager::getVoiceTemplates();
        $result = Sms::voice($code)->template($templates)->data(['code' => $code])->to($mobile)->send();
        if ($result === null || $result['success']) {
            $data = Manager::getSentInfo();
            $data['sent'] = true;
            $data['mobile'] = $mobile;
            $data['code'] = $code;
            $data['deadline_time'] = time() + (15 * 60);
            Manager::storeSentInfo($token, $data);
            Manager::storeCanResendAfter($token, $seconds);
            $verifyResult = Manager::genResult(true, 'voice_send_success');
        } else {
            $verifyResult = Manager::genResult(false, 'voice_send_failure');
        }

        return response()->json($verifyResult);
    }

    public function postSendCode(Request $request)
    {
        $verifyResult = Manager::validate($request->all());
        if (!$verifyResult['success']) {
            return response()->json($verifyResult);
        }

        $mobile = $request->input('mobile', null);
        $token = $request->input('token', null);
        $seconds = $request->input('seconds', 60);

        $code = Manager::generateCode();
        $minutes = Manager::getCodeValidTime();
        $templates = Manager::getSmsTemplates();
        $contentTemp = Manager::getVerifySmsContent();
        $content = Manager::vsprintf($contentTemp, [$code, $minutes]);
        $result = Sms::make($templates)->to($mobile)->data(['code' => $code, 'minutes' => $minutes])->content($content)->send();
        if ($result === null || $result['success']) {
            $data = Manager::getSentInfo();
            $data['sent'] = true;
            $data['mobile'] = $mobile;
            $data['code'] = $code;
            $data['deadline_time'] = time() + ($minutes * 60);
            Manager::storeSentInfo($token, $data);
            Manager::storeCanResendAfter($token, $seconds);
            $verifyResult = Manager::genResult(true, 'sms_send_success');
        } else {
            $verifyResult = Manager::genResult(false, 'sms_send_failure');
        }

        return response()->json($verifyResult);
    }

    public function getInfo(Request $request, $token = null)
    {
        $html = '<meta charset="UTF-8"/><h2 align="center" style="margin-top: 20px;">Laravel Sms</h2>';
        $html .= '<p style="color: #666;"><a href="https://github.com/toplan/laravel-sms" target="_blank">laravel-sms源码</a>托管在GitHub，欢迎你的使用。如有问题和建议，欢迎提供issue。</p>';
        $html .= '<hr>';
        $html .= '<p>你可以在调试模式(设置config/app.php中的debug为true)下查看到存储在session中的验证码短信相关数据(方便你进行调试)：</p>';
        echo $html;
        $token = $token ?: $request->input('token', null);
        if (config('app.debug')) {
            dump(Manager::retrieveAll($token));
        } else {
            echo '<p align="center" style="color: red;">现在是非调试模式，无法查看验证码短信数据</p>';
        }
    }
}
