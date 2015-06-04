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
     * send a template sms
     * @param       $tempId
     * @param       $to
     * @param array $data
     *
     * @return mixed
     */
    public abstract function sendTemplateSMS($tempId, $to, Array $data);

}
