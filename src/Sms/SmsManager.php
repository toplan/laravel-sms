<?php namespace Toplan\Sms;

use Config;
use Doctrine\Instantiator\Exception\InvalidArgumentException;
use Session;

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

    protected $smsData;

    /**
     * construct
     * @param $app
     */
	public function __construct($app)
    {
        $this->app = $app;
        $this->init();
    }

    /**
     * sms manager init
     */
    private function init()
    {
        $data = [
                'sent' => false,
                'mobile' => '',
                'code' => '',
                'deadline_time' => 0,
                'rules' => Config::get('sms::rules'),
            ];
        $this->smsData = $data;
    }

    /**
     * get data
     * 获取发送相关信息
     * @return mixed
     */
    public function getSmsData()
    {
        return $this->smsData;
    }

    /**
     * set sent data
     * 设置发送相关信息
     * @param array $data
     */
    public function setSmsData(Array $data)
    {
        $this->smsData = $data;
    }

    public function storeSmsDataToSession(Array $data = [])
    {
        $data = $data ?: $this->smsData;
        $this->smsData = $data;
        Session::put($this->getSessionKey(), $data);
    }

    public function getSmsDataFromSession()
    {
        return Session::get($this->getSessionKey(), []);
    }

    /**
     * get rule by name
     * @param $name
     *
     * @return mixed
     */
    public function getRule($name)
    {
        $data = $this->getSmsData();
        $ruleName = $data['rules']["$name"]['choose_rule'];
        return $data['rules']["$name"]["$ruleName"];
    }

    /**
     * set rule
     * @param $name
     * @param $value
     *
     * @return mixed
     */
    public function rule($name, $value)
    {
        $data = $this->getSmsData();
        $data['rules']["$name"]['choose_rule'] = $value;
        $this->setSmsData($data);
        return $data;
    }

    public function getChooseRule($name)
    {
        $data = $this->getSmsData();
        return $data['rules']["$name"]['choose_rule'];
    }

    /**
     * is verify
     * @param string $name
     *
     * @return mixed
     */
    public function isCheck($name = 'mobile')
    {
        $data = $this->getSmsData();
        return $data['rules']["$name"]['is_check'];
    }

    public function getTempIdForVerifySms()
    {
        $tempId = Config::get('sms::templateIdForVerifySms');
        if ($tempId) {
            return $tempId;
        }
        throw new \InvalidArgumentException("config key [templateIdForVerifySms] empty. Please set 'templateIdForVerifySms' in config file");
    }

    public function generateCode($length = null, $characters = null)
    {
        $length = $length ?: (int) Config::get('sms::codeLength');
        $characters = $characters ?: '123456789';
        $charLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; ++$i) {
            $randomString .= $characters[mt_rand(0, $charLength - 1)];
        }
        return $randomString;
    }

    public function getCodeValidTime()
    {
        return Config::get('sms::codeValidTime');//minutes
    }

    /**
     * get session key
     * @return mixed
     */
    public function getSessionKey()
    {
        return Config::get('sms::sessionKey');
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
     * @return string
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
        $config['smsWorker'] = Config::get('sms::smsWorker', 'Toplan\Sms\SmsWorker');
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
