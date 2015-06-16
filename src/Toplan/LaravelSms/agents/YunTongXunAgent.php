<?php namespace Toplan\Sms;

use REST;

class YunTongXunAgent extends Agent{

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
        if ($result == null || $result->statusCode != 0) {
            //sent failed
            $this->result['success'] = false;
            $this->result['info'] = $this->currentAgentName . ':' . $result->statusCode;
            $this->result['code'] = $result->statusCode;
        } elseif ($result->statusCode == 0) {
            //sent success
            $this->result['success'] = true;
            $this->result['info'] = $this->currentAgentName . ':' . $result->statusCode;
            $this->result['code'] = $result->statusCode;
        }
    }

    public function sendContentSms($to, $content)
    {
        return null;
    }

}