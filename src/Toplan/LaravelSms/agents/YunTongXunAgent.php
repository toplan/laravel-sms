<?php
namespace Toplan\Sms;

use REST;

class YunTongXunAgent extends Agent
{
    public function sendSms($tempId, $to, Array $data, $content)
    {
        //云通讯目前只支持模板短信
        $this->sendTemplateSms($tempId, $to, $data);
    }

    public function sendTemplateSms($tempId, $to, Array $data)
    {
        // 初始化REST SDK
        $rest = new REST(
            $this->serverIP,
            $this->serverPort,
            $this->softVersion,
            storage_path('logs/sms-log.txt')
        );
        $rest->setAccount($this->accountSid, $this->accountToken);
        $rest->setAppId($this->appId);
        // 发送模板短信
        $result = $rest->sendTemplateSMS($to, array_values($data), $tempId);
        if ($result != null && $result->statusCode == 0) {
            $this->result['success'] = true;
        }
        $this->result['info'] = $this->currentAgentName . ':' . $result->statusCode;
        $this->result['code'] = $result->statusCode;
    }

    public function sendContentSms($to, $content)
    {
        return null;
    }

    public function voiceVerify($to, $code)
    {
        // 初始化REST SDK
        $rest = new REST(
            $this->serverIP,
            $this->serverPort,
            $this->softVersion,
            storage_path('logs/sms-log.txt')
        );
        $rest->setAccount($this->accountSid, $this->accountToken);
        $rest->setAppId($this->appId);

        // 调用语音验证码接口
        $playTimes = 3;
        $respUrl = null;
        $lang = 'zh';
        $userData = null;
        $result = $rest->voiceVerify($code, $playTimes, $to, null, $respUrl, $lang, $userData, null, null);
        if ($result == null) {
            return $this->result;
        }
        if ( $result->statusCode == 0 ) {
            $this->result['success'] = true;
            // 获取返回信息
            //$voiceVerify = $result->VoiceVerify;
            //echo "callSid:".$voiceVerify->callSid."<br/>";
            //echo "dateCreated:".$voiceVerify->dateCreated."<br/>";
        }
        $this->result['info'] = $this->currentAgentName . ':' . $result->statusMsg;
        $this->result['code'] = $result->statusCode;
        return $this->result;
    }
}