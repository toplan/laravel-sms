<?php namespace Toplan\Sms;

use Illuminate\Support\Facades\Config;

class SmsManager {

    /**
     * the application instance
     * @var
     */
    protected $app;

    /**
     * agent instances
     * @var
     */
    protected $agents;

    /**
     * construct
     * @param $app
     */
	public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * get the default agent name
     * @return mixed
     */
    public function getDefaultAgent()
    {
        return Config::get('sms::agent');
    }

    /**
     * set the default agent name
     * @param $name
     */
    public function setDefaultAgent($name)
    {
        Config::set('sms::agent', $name);
        return Config::get('sms::agent', $name);
    }

    /**
     * get a agent instance
     * @param null $agentName
     *
     * @return mixed
     */
    public function agent($agentName = null)
    {
        $agentName = $agentName ?: $this->getDefaultAgent();
        if (! isset($this->agents[$agentName])) {
            $this->agents[$agentName] = $this->createAgent($agentName);
        }
        return $this->agents[$agentName];
    }

    /**
     * create a agent instance by agent name
     * @param $agentName
     *
     * @return mixed
     */
    public function createAgent($agentName)
    {
        $method = 'create'.ucfirst($agentName).'Agent';
        if (method_exists($this, $method)) {
            $agentConfig = $this->getAgentConfig($agentName);
            return $this->$method($agentConfig);
        }
        throw new \InvalidArgumentException("Agent [$agentName] not supported.");
    }

    /**
     * get agent config
     * @param $agentName
     *
     * @return array
     */
    public function getAgentConfig($agentName)
    {
        $config = Config::get("sms::$agentName") ?: [];
        $config['smsSendQueue'] = Config::get('sms::smsSendQueue');
        $config['smsWorker'] = Config::get('sms::smsWorker') ?: 'Toplan\Sms\SmsWorker';
        if ( ! class_exists($config['smsWorker'])) {
            throw new \InvalidArgumentException("Worker [" . $config['worker'] . "] not support.");
        }
        return $config;
    }

    /**
     * create a YunTongXun(云通讯) agent instance
     * YunTongXun`s official website:
     * http://www.yuntongxun.com/
     * @param $agentConfig
     * @return YunTongXunAgent
     */
    public function createYunTongXunAgent(Array $agentConfig)
    {
        return new YunTongXunAgent($agentConfig);
    }

}
