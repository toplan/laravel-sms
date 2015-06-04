<?php namespace Toplan\Sms;

use REST;

class YunTongXunAgent extends Agent{

    public function sendTemplateSms($tempId, $to, Array $data)
    {
        // 初始化REST SDK
        $rest = new REST(
            $this->config["serverIP"],
            $this->config["serverPort"],
            $this->config["softVersion"],
            storage_path('logs/sms-log.txt')
        );
        $rest->setAccount($this->config["accountSid"], $this->config["accountToken"]);
        $rest->setAppId($this->config["appId"]);

        // 发送模板短信
        $result = $rest->sendTemplateSMS($to, $data, $tempId);
        if ($result == null || $result->statusCode != 0) {
            //sent failed
            $this->result['success'] = false;
            $this->result['info'] = $result->statusCode;
            $this->result['code'] = $result->statusCode;
            return $this->result;
        } elseif ($result->statusCode == 0) {
            //sent success
            $this->result['success'] = true;
            $this->result['info'] = $result->statusCode;
            $this->result['code'] = $result->statusCode;
            return $this->result;
        }
        //default return
        return $this->result;
    }

}