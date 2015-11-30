<?php
namespace Toplan\Sms;

use \Session;
class SmsManager
{
    /**
     * sent info
     * @var
     */
    protected $sentInfo;

    /**
     * unique key for store key
     * store key = storePrefixKey + '_' + uniqueKey
     * @var null
     */
    protected $uniqueKey = null;

    /**
     * storage
     * @var null
     */
    protected static $storage = null;

    /**
     * construct
     */
	public function __construct()
    {
        $this->init();
    }

    /**
     * sms manager init
     */
    private function init()
    {
        $info = [
                'sent' => false,
                'mobile' => '',
                'code' => '',
                'deadline_time' => 0,
                'verify' => config('laravel-sms.verify'),
            ];
        $this->sentInfo = $info;
    }

    /**
     * get sent info
     * @return mixed
     */
    public function getSentInfo()
    {
        return $this->sentInfo;
    }

    /**
     * set sent data
     * @param array $key
     * @param null $data
     */
    public function setSentInfo($key, $data = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->setSentInfo($k, $v);
            }
        } elseif (array_key_exists("$key", $this->sentInfo)) {
            $this->sentInfo["$key"] = $data;
        }
    }

    /**
     * get storage
     * @return null
     * @throws LaravelSmsException
     */
    public function getStorage()
    {
        if (self::$storage) {
            return self::$storage;
        }
        $className = config('laravel-sms.storage');
        if (class_exists($className)) {
            self::$storage = new $className();
            return self::$storage;
        }
        throw new LaravelSmsException("Generator storage failed, don`t find class [$className]");
    }

    /**
     * put sms sent info to storage
     * @param       $uniqueKey
     * @param array $data
     *
     * @throws LaravelSmsException
     */
    public function storeSentInfo($uniqueKey, $data = [])
    {
        if (is_array($uniqueKey)) {
            $data = $uniqueKey;
            $uniqueKey = null;
        }
        if ($uniqueKey) {
            $this->uniqueKey = $uniqueKey;
        }
        if (is_array($data)) {
            $this->setSentInfo($data);
        }
        $key = $this->getStoreKey();
        $storage = $this->getStorage();
        $storage->set($key, $this->getSentInfo());
    }

    /**
     * get sms sent info from storage
     * @return mixed
     */
    public function getSentInfoFromStorage()
    {
        $key = $this->getStoreKey();
        $storage = $this->getStorage();
        return $storage->get($key, []);
    }

    /**
     * remove sms data from session
     */
    public function forgetSentInfoFromStorage()
    {
        $key = $this->getStoreKey();
        $storage = $this->getStorage();
        $storage->forget($key);
    }

    /**
     * get store key
     * @param String $str
     * @return mixed
     */
    public function getStoreKey($str = '')
    {
        $prefix = config('laravel-sms.storePrefixKey', 'laravel_sms_info');
        if ($str) {
            return $prefix . ((String) $str);
        }
        if ($this->uniqueKey) {
            return $prefix . ((String) $this->uniqueKey);
        }
        return $prefix;
    }


    //--------------------------------下面还未修改

    /**
     * Is there a designated validation rule
     * 是否有指定的验证规则
     * @param $name
     * @param $ruleName
     *
     * @return bool
     */
    public function hasRule($name, $ruleName)
    {
        $data = $this->getSmsData();
        return isset($data['verify']["$name"]['rules']["$ruleName"]);
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
        $ruleName = $data['verify']["$name"]['choose_rule'];
        return $data['verify']["$name"]['rules']["$ruleName"];
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
        $data['verify']["$name"]['choose_rule'] = $value;
        $this->setSmsData($data);
        return $data;
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
        return $data['verify']["$name"]['enable'];
    }

    /**
     * get verify sms template id
     * @param String $agentName
     * @return mixed
     */
    public function getVerifySmsTemplateId($agentName = null)
    {
        $agentName = $agentName ?: $this->getDefaultAgent();
        $agentConfig = config('laravel-sms.'.$agentName, null);
        if ($agentConfig && isset($agentConfig['verifySmsTemplateId'])) {
            return $agentConfig['verifySmsTemplateId'];
        }
        return '';
    }

    /**
     * get verify sms content
     * @return mixed
     */
    public function getVerifySmsContent()
    {
        return config('laravel-sms.verifySmsContent');
    }

    /**
     * generate verify code
     * @param null $length
     * @param null $characters
     *
     * @return string
     */
    public function generateCode($length = null, $characters = null)
    {
        $length = $length ?: (int) config('laravel-sms.codeLength');
        $characters = $characters ?: '0123456789';
        $charLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; ++$i) {
            $randomString .= $characters[mt_rand(0, $charLength - 1)];
        }
        return $randomString;
    }

    /**
     * get code valid time (minutes)
     * @return mixed
     */
    public function getCodeValidTime()
    {
        return config('laravel-sms.codeValidTime');
    }


    /**
     * set can be send sms time
     * @param int $seconds
     *
     * @return int
     */
    public function setCanSendTime($seconds = 60)
    {
        $key = $this->getStoreKey('_CanSendTime');
        $time = time() + $seconds;
        Session::put($key, $time);
        return $time;
    }

    /**
     * get can be send sms time
     * @return mixed
     */
    public function getCanSendTime()
    {
        $key = $this->getSessionKey('_CanSendTime');
        return Session::get($key, 0);
    }

    /**
     * can be send sms
     * @return bool
     */
    public function canSend()
    {
        return $this->getCanSendTime() <= time();
    }
}
