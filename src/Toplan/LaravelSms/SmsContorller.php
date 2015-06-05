<?php namespace Toplan\Sms;

use \Input;
use \SmsManager;
use \Validator;
use Illuminate\Routing\Controller;

class SmsController extends Controller {

    public $smsModel;

    public function __construct()
    {
        $this->smsModel = config('laravel-sms.smsModel', 'Toplan/Sms/Sms');
    }

    public function getSendCode($rule, $mobile = '')
    {
        $vars = [];
        $input = ['mobile' => $mobile];
        $vars['success'] = false;
        //验证手机号合法性-------------------------------
        //设置手机号验证规则
        if (SmsManager::hasRule('mobile', $rule)) {
            SmsManager::rule('mobile', $rule);
        }
        $validator = Validator::make($input, [
            'mobile' => 'required|mobile'
        ]);
        if ($validator->fails()) {
            $vars['msg'] = '手机号格式错误，请输入正确的11位手机号';
            $vars['type'] = 'mobile_error';
            return Response::json($vars);
        }
        if (SmsManager::isCheck('mobile')) {
            $validator = Validator::make($input, [
                'mobile' => SmsManager::getRule('mobile')
            ]);
            if ($validator->fails()) {
                if ($rule == 'check_mobile_unique') {
                    $vars['msg'] = '该手机号码已存在';
                } elseif ($rule == 'check_mobile_exists') {
                    $vars['msg'] = '不存在此手机号码';
                } else {
                    $vars['msg'] = '抱歉，你的手机号未通过合法性检测';
                }
                $vars['type'] = 'mobile_error';
                return response()->json($vars);
            }
        }
        //------------------------------------------

        // 发送短信----------------------------------
        $code = SmsManager::generateCode();
        $minutes = SmsManager::getCodeValidTime();
        $tempId = SmsManager::getTempIdForVerifySms();
        $sms = new $this->smsModel;
        $result = $sms->template($tempId)->to($mobile)->data([$code, $minutes])->send();
        if ($result) {
            $data = SmsManager::getSmsData();
            $data['sent'] = true;
            $data['mobile'] = $mobile;
            $data['code'] = $code;
            $data['deadline_time'] = time() + ($minutes * 60);
            SmsManager::storeSmsDataToSession($data);
            $vars['success'] = true;
            $vars['msg'] = '短信发送成功，请注意查收';
            $vars['type'] = 'sent_success';
        } else {
            $vars['msg'] = '短信发送失败，请重新获取';
            $vars['type'] = 'sent_failed';
        }
        return response()->json($vars);
    }

    public function getInfo()
    {
        echo ('<p>hello, welcome to laravel-sms for l5.  current time:'.time().'</p><hr><p style="color: green;">sms data in session:</p> <br>');
        dd(SmsManager::getSmsDataFromSession());
    }

}