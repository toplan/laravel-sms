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
        $token = $request->input('token', null);
        $mobile = $request->input('mobile', null);
        $seconds = $request->input('seconds', 60);

        if (!Manager::sendable($token)) {
            return response()->json(Manager::genResult(false, 'request_invalid', [$seconds]));
        }

        $res = Manager::validate($request->all());
        if (!$res['success']) {
            return response()->json($res);
        }

        $code = Manager::generateCode();
        $minutes = Manager::getCodeValidMinutes();
        $templates = Manager::getVoiceTemplates();
        $result = Sms::voice($code)->template($templates)
            ->data(['code' => $code])->to($mobile)->send();

        if ($result === null || $result === true || (isset($result['success']) && $result['success'])) {
            $data = Manager::getSentInfo();
            $data['sent'] = true;
            $data['mobile'] = $mobile;
            $data['code'] = $code;
            $data['deadline'] = time() + ($minutes * 60);
            Manager::storeSentInfo($token, $data);
            Manager::storeCanResendAfter($token, $seconds);
            $res = Manager::genResult(true, 'voice_send_success');
        } else {
            $res = Manager::genResult(false, 'voice_send_failure');
        }

        return response()->json($res);
    }

    public function postSendCode(Request $request)
    {
        $token = $request->input('token', null);
        $mobile = $request->input('mobile', null);
        $seconds = $request->input('seconds', 60);

        if (!Manager::sendable($token)) {
            return response()->json(Manager::genResult(false, 'request_invalid', [$seconds]));
        }

        $res = Manager::validate($request->all());
        if (!$res['success']) {
            return response()->json($res);
        }

        $code = Manager::generateCode();
        $minutes = Manager::getCodeValidMinutes();
        $templates = Manager::getSmsTemplates();
        $content = Manager::generateSmsContent([$code, $minutes]);
        $result = Sms::make($templates)->to($mobile)
            ->data(['code' => $code, 'minutes' => $minutes])
            ->content($content)->send();

        if ($result === null || $result === true || (isset($result['success']) && $result['success'])) {
            $data = Manager::getSentInfo();
            $data['sent'] = true;
            $data['mobile'] = $mobile;
            $data['code'] = $code;
            $data['deadline'] = time() + ($minutes * 60);
            Manager::storeSentInfo($token, $data);
            Manager::storeCanResendAfter($token, $seconds);
            $res = Manager::genResult(true, 'sms_send_success');
        } else {
            $res = Manager::genResult(false, 'sms_send_failure');
        }

        return response()->json($res);
    }

    public function getInfo(Request $request, $token = null)
    {
        $html = '<meta charset="UTF-8"/><h2 align="center" style="margin-top: 30px;margin-bottom: 0;">Laravel Sms</h2>';
        $html .= '<p style="margin-bottom: 30px;font-size: 13px;color: #888;" align="center">' . SmsManager::VERSION . '</p>';
        $html .= '<p><a href="https://github.com/toplan/laravel-sms" target="_blank">laravel-sms源码</a>托管在GitHub，欢迎你的使用。如有问题和建议，欢迎提供issue。</p>';
        $html .= '<p>本页面路由: scheme://your-domain/sms/info<span style="color: #aaa;">/{token?}</spanst></p>';
        $html .= '<hr>';
        $html .= '<p>你可以在调试模式(设置config/app.php中的debug为true)下查看到存储在存储器中的验证码短信/语音相关数据:</p>';
        echo $html;
        $token = $token ?: $request->input('token', null);
        if (config('app.debug')) {
            dump(Manager::retrieveAll($token));
        } else {
            echo '<p align="center" style="color: red;">现在是非调试模式，无法查看验证码短信数据</p>';
        }
    }
}
