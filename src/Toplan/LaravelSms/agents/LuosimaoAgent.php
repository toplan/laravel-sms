<?php namespace Toplan\Sms;

class LuosimaoAgent extends Agent {

    public function sendSms($tempId, $to, Array $data, $content)
    {
        $this->sendContentSms($to, $content);
    }

    public function sendContentSms($to, $content)
    {
        $url = 'http://sms-api.luosimao.com/v1/send.json';
        $apikey = $this->apikey;
        $optData = [
            'mobile' => $to,
            'message' => $content
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "$url");

        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);

        curl_setopt($ch, CURLOPT_HTTPAUTH , CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD  , 'api:key-' . $apikey);

        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $optData);

        $res = curl_exec( $ch );
        curl_close( $ch );

        $data = json_decode($res, true);
        if ($data['error'] == 0) {
            $this->result['success'] = true;
        }
        $this->result['info'] = $this->currentAgentName . ':' . $data['msg'] . "({$data['error']})";
        $this->result['code'] = $data['error'];
    }

    public function sendTemplateSms($tempId, $to, Array $data)
    {
        return null;
    }

}
