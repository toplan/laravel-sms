<?php namespace Toplan\Sms;

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
        if (isset($this->config['isResendFailedSmsInQueue'])) {
            return $this->config['isResendFailedSmsInQueue'];
        }
        return false;
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
     * @param array $tempIds
     * @param       $to
     * @param array $data
     * @param       $content
     *
     * @return array|null
     */
    public function sms(Array $tempIds, $to, Array $data, $content)
    {
        $tempId = '';
        if (isset($tempIds[$this->config['currentAgentName']])) {
            $tempId = $tempIds[$this->config['currentAgentName']];
        }
        $this->sendSms($tempId, $to, $data, $content);
        if ( ! $this->result['success'] && $this->config['nextAgentEnable']) {
            $result = $this->tryNextAgent($tempIds, $to, $data, $content);
            if ($result) {
                $result['info'] = $this->result['info'] . "##" . $result['info'];
                return $result;
            }
        }
        return $this->result;
    }

    /**
     * resend sms by sub agent
     * @param array $tempIds
     * @param       $to
     * @param array $data
     * @param       $content
     *
     * @return null
     */
    public function tryNextAgent(Array $tempIds, $to, Array $data, $content)
    {
        if ( ! $this->config['nextAgentName']) {
            return null;
        }
        $agent = app('SmsManager')->agent($this->config['nextAgentName']);
        $result = $agent->sms($tempIds, $to, $data, $content);
        return $result;
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
     * @param       $url
     * @param array $query
     * @param       $port
     *
     * @return mixed
     */
    function sockPost($url, $query, $port = 80){
        $data = "";
        $info = parse_url($url);
        $fp   = fsockopen($info["host"], $port, $errno, $errstr, 30);
        if ( ! $fp) {
            return $data;
        }
        $head  = "POST ".$info['path']." HTTP/1.0\r\n";
        $head .= "Host: ".$info['host']."\r\n";
        $head .= "Referer: http://".$info['host'].$info['path']."\r\n";
        $head .= "Content-type: application/x-www-form-urlencoded\r\n";
        $head .= "Content-Length: ".strlen(trim($query))."\r\n";
        $head .= "\r\n";
        $head .= trim($query);
        $write = fputs($fp,$head);
        $header = "";
        while ($str = trim(fgets($fp, 4096))) {
            $header .= $str;
        }
        while ( ! feof($fp)) {
            $data .= fgets($fp, 4096);
        }
        return $data;
    }

    /**
     * overload object attribute
     * @param $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        if (array_key_exists($name, $this->config)) {
            return $this->config["$name"];
        }
        return null;
    }
}
