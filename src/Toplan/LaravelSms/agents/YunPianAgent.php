<?php namespace Toplan\Sms;

class YunPianAgent extends Agent {

    public function sendSms($tempId, $to, Array $data, $content)
    {
        $this->sendContentSms($to, $content);
    }

    public function sendContentSms($to, $content)
    {
        $url = 'http://yunpian.com/v1/sms/send.json';
        $apikey = $this->apikey;
        $content = urlencode("$content");

        $postString = "apikey=$apikey&text=$content&mobile=$to";
        $response = $this->sockPost($url, $postString);

        $data = json_decode($response, true);
        if ($data['code'] == 0) {
            $this->result['success'] = true;
        }
        $this->result['info'] = $this->currentAgentName . ':' . $data['msg'];
        $this->result['code'] = $data['code'];
    }

    public function sendTemplateSms($tempId, $to, Array $data)
    {
        return null;
    }

}
