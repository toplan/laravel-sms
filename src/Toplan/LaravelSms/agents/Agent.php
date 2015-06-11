<?php namespace Toplan\Sms;

use \SmsManager;

Abstract class Agent {

    /**
     * agent config
     * @var array
     */
    protected $config;

    /**
     * sent result info
     * @var array
     */
    protected $result = [
        'success' => false,
        'info'  => '',
        'code'  => 0
    ];

    /**
     * construct for create a instance
     * @param array $config
     */
    public function __construct(Array $config = [])
    {
        $this->config = $config;
    }

    /**
     * get queue worker
     * @return mixed
     */
    public function getWorkerName()
    {
        return $this->config['smsWorker'];
    }

    /**
     * 是否开启发送队列
     * @return mixed
     */
    public function isPushToQueue()
    {
        return $this->config['smsSendQueue'];
    }

    /**
     * 是否重复发送队列任务中失败的短信
     * @return mixed
     */
    public function isResendFailedSmsInQueue()
    {
        return $this->config['isResendFailedSmsInQueue'];
    }

    /**
     * 开启发送队列
     */
    public function openQueue()
    {
        $this->config['smsSendQueue'] = true;
    }

    /**
     * 关闭发送队列
     */
    public function closeQueue()
    {
        $this->config['smsSendQueue'] = false;
    }

    /**
     * sms send entry
     * @param       $tempId
     * @param       $to
     * @param array $data
     * @param       $content
     *
     * @return array|null
     */
    public function sms($tempId, $to, Array $data, $content)
    {
        $this->sendSms($tempId, $to, $data, $content);
        if ($this->result['success']) {
            return $this->result;
        } elseif ($this->config['nextAgentEnable']) {
            $result = $this->tryNextAgent($tempId, $to, $data, $content);
            if ( ! $result) {
                return $this->result;
            } else {
                $result['info'] = $this->result['info'] . "##" . $result['info'];
                return $result;
            }
        }
        return $this->result;
    }

    /**
     * resend sms use next agent
     * @param       $tempId
     * @param       $to
     * @param array $data
     * @param       $content
     *
     * @return null
     */
    public function tryNextAgent($tempId, $to, Array $data, $content)
    {
        if ( ! $this->config['nextAgentName']) {
            return null;
        }
        $agent = SmsManager::agent($this->config['nextAgentName']);
        return $agent->sms($tempId, $to, $data, $content);
    }

    /**
     * sms send process entry
     * @param       $tempId
     * @param       $to
     * @param array $data
     * @param       $content
     *
     * @return mixed
     */
    public abstract function sendSms($tempId, $to, Array $data, $content);

    /**
     * content sms send process
     * @param $to
     * @param $content
     *
     * @return mixed
     */
    public abstract function sendContentSms($to, $content);

    /**
     * template sms send process
     * @param       $tempId
     * @param       $to
     * @param array $data
     *
     * @return mixed
     */
    public abstract function sendTemplateSms($tempId, $to, Array $data);

    /**
     * http post request
     * this function`s code copy from http://www.yunpian.com/api/demo.html
     * @param       $url
     * @param array $query
     *
     * @return mixed
     */
    function sockPost($url,$query){
        $data = "";
        $info=parse_url($url);
        $fp=fsockopen($info["host"],80,$errno,$errstr,30);
        if(!$fp){
            return $data;
        }
        $head="POST ".$info['path']." HTTP/1.0\r\n";
        $head.="Host: ".$info['host']."\r\n";
        $head.="Referer: http://".$info['host'].$info['path']."\r\n";
        $head.="Content-type: application/x-www-form-urlencoded\r\n";
        $head.="Content-Length: ".strlen(trim($query))."\r\n";
        $head.="\r\n";
        $head.=trim($query);
        $write=fputs($fp,$head);
        $header = "";
        while ($str = trim(fgets($fp,4096))) {
            $header.=$str;
        }
        while (!feof($fp)) {
            $data .= fgets($fp,4096);
        }
        return $data;
    }

}
