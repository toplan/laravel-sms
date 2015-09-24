<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2015/7/6
 * Time: 13:39
 */

namespace Toplan\Sms;

class UcpaasAgent extends Agent
{

    /**
     * sms send process entry
     * @param       $tempId
     * @param       $to
     * @param array $data
     * @param       $content
     *
     * @return mixed
     */
    public function sendSms($tempId, $to, Array $data, $content)
    {
        $this->sendTemplateSms($tempId, $to, $data);
    }

    /**
     * content sms send process
     * @param $to
     * @param $content
     *
     * @return mixed
     */
    public function sendContentSms($to, $content)
    {
        return null;
    }

    /**
     * template sms send process
     * @param       $tempId
     * @param       $to
     * @param array $data
     *
     * @return mixed
     */
    public function sendTemplateSms($tempId, $to, Array $data)
    {
        $config = [
            'accountsid' => $this->accountSid,
            'token' => $this->accountToken
        ];
        $ucpaas = new \Ucpaas($config);
        $response = $ucpaas->templateSMS($this->appId, $to, ( $tempId ?: $this->verifySmsTemplateId ) , implode(',',$data));
        $result = json_decode($response);
        if ($result != null && $result->resp->respCode == '000000') {
            $this->result['success'] = true;
        }
        $this->result['info'] = $this->currentAgentName . ':' . $result->resp->respCode;
        $this->result['code'] = $result->resp->respCode;
    }

    public function voiceVerify($to, $code)
    {
        $config = [
            'accountsid' => $this->accountSid,
            'token' => $this->accountToken
        ];
        $ucpass = new \Ucpaas($config);
        $response = $ucpass->voiceCode($this->appId, $code, $to, $type = 'json');
        $result = json_decode($response);
        if ($result == null) {
            return $this->result;
        }
        if ($result->resp->respCode == '000000') {
            $this->result['success'] = true;
        }
        $this->result['info'] = $this->currentAgentName . ':' . $result->resp->respCode;
        $this->result['code'] = $result->resp->respCode;
        return $this->result;
    }
}
