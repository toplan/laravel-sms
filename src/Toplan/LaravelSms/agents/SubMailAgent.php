<?php namespace Toplan\Sms;

class SubMailAgent extends Agent {

    public function sendSms($tempId, $to, Array $data, $content)
    {
        $this->sendTemplateSms($tempId, $to, $data);
    }

    public function sendContentSms($to, $content)
    {
        return null;
    }

    public function sendTemplateSms($tempId, $to, Array $data)
    {
        $url = 'https://api.submail.cn/message/xsend.json';
        $appid = $this->appid;
        $signature = $this->signature;
        $vars = json_encode($data);

        $postString = "appid=$appid&project=$tempId&to=$to&signature=$signature&vars=$vars";
        $response = $this->sockPost($url, $postString);

        $data = json_decode($response, true);
        if ($data['status'] == 'success') {
            $this->result['success'] = true;
        }
        $this->result['info'] = $this->currentAgentName . ':' . $data['msg'];
        $this->result['code'] = $data['code'];
    }

}