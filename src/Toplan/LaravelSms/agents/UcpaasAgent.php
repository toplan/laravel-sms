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
        $response = $ucpaas->templateSMS($this->appId, $to, $tempId, $data);
        $result = json_decode($response);

        if ($result == null || $result->resp->respCode != '000000') {
            //sent failed
            $this->result['success'] = false;
            $this->result['info'] = $this->currentAgentName . ':' . $result->resp->respCode;
            $this->result['code'] = $result->resp->respCode;
        } elseif ($result->resp->respCode == '000000') {
            //sent success
            $this->result['success'] = true;
            $this->result['info'] = $this->currentAgentName . ':' . $result->resp->respCode;
            $this->result['code'] = $result->resp->respCode;
        }
    }
}