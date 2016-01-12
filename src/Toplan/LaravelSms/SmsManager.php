<?php

namespace Toplan\Sms;

use Toplan\PhpSms\Sms;
use URL;
use Validator;

class SmsManager
{
    const CUSTOM_RULE_FLAG = '_custom_rule_in_server';

    const CAN_RESEND_UNTIL = '_can_resend_until';

    const SMS_INFO_KEY = '_sms_info';

    /**
     * sent info
     *
     * @var
     */
    protected $sentInfo;

    /**
     * storage
     *
     * @var
     */
    protected static $storage;

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
        $this->sentInfo = [
                'sent'          => false,
                'mobile'        => '',
                'code'          => '',
                'deadline_time' => 0,
                'verify'        => config('laravel-sms.verify', []),
            ];
    }

    /**
     * get sent info
     *
     * @return mixed
     */
    public function getSentInfo()
    {
        return $this->sentInfo;
    }

    /**
     * set sent data
     *
     * @param array $key
     * @param null  $data
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
     *
     * @throws LaravelSmsException
     *
     * @return null
     */
    public function storage()
    {
        if (self::$storage) {
            return self::$storage;
        }
        $className = config('laravel-sms.storage', 'Toplan\Sms\SessionStorage');
        if (class_exists($className)) {
            self::$storage = new $className();

            return self::$storage;
        }
        throw new LaravelSmsException("Generator storage failed, don`t find class [$className]");
    }

    /**
     * put sms sent info to storage
     *
     * @param       $token
     * @param array $data
     *
     * @throws LaravelSmsException
     */
    public function storeSentInfo($token, $data = [])
    {
        if (is_array($token)) {
            $data = $token;
            $token = null;
        }
        if (is_array($data)) {
            $this->setSentInfo($data);
        }
        $key = $this->getStoreKey($token, self::SMS_INFO_KEY);
        $this->storage()->set($key, $this->getSentInfo());
    }

    /**
     * retrieve sms sent info from storage
     *
     * @param  $token
     *
     * @return mixed
     */
    public function retrieveSentInfo($token = null)
    {
        $key = $this->getStoreKey($token, self::SMS_INFO_KEY);

        return $this->storage()->get($key, []);
    }

    /**
     * forget sms sent info from storage
     *
     * @param  $token
     */
    public function forgetSentInfo($token = null)
    {
        $key = $this->getStoreKey($token, self::SMS_INFO_KEY);
        $this->storage()->forget($key);
    }

    /**
     * retrieve debug info from storage
     *
     * @param null $token
     *
     * @throws LaravelSmsException
     *
     * @return mixed
     */
    public function retrieveDebugInfo($token = null)
    {
        $key = $this->getStoreKey($token);

        return $this->storage()->get($key, []);
    }

    /**
     * get store key
     * support split character:'.', ':', '+', '*'
     *
     * @return mixed
     */
    public function getStoreKey()
    {
        $prefix = config('laravel-sms.storePrefixKey', 'laravel_sms_info');
        $args = func_get_args();
        $split = '.';
        $appends = [];
        foreach ($args as $arg) {
            $arg = (String) $arg;
            if ($arg) {
                if (preg_match('/^[.:\+\*]+$/', $arg)) {
                    $split = $arg;
                } elseif (preg_match('/^[^.:\+\*\s]+$/', $arg)) {
                    array_push($appends, $arg);
                }
            }
        }
        if ($appends) {
            $prefix .= $split . implode($split, $appends);
        }

        return $prefix;
    }

    /**
     * get verify config
     *
     * @param $name
     *
     * @throws LaravelSmsException
     *
     * @return mixed
     */
    protected function getVerifyData($name)
    {
        if (!$name) {
            return $this->sentInfo['verify'];
        }
        if ($this->sentInfo['verify']["$name"]) {
            return $this->sentInfo['verify']["$name"];
        }
        throw new LaravelSmsException("Don`t find [$name] verify data in config file:laravel-sms.php");
    }

    /**
     * whether contain a character validation rule
     *
     * @param $name
     * @param $ruleName
     *
     * @return bool
     */
    public function hasRule($name, $ruleName)
    {
        $data = $this->getVerifyData($name);

        return isset($data['rules']["$ruleName"]);
    }

    /**
     * get real rule by name
     *
     * @param $ruleAlias
     * @param $token
     *
     * @return mixed
     */
    public function getRealMobileRule($ruleAlias = '', $token = null)
    {
        $realRule = '';
        //尝试使用用户从客户端传递过来的rule
        if ($this->setUsedRuleAlias('mobile', $ruleAlias)) {
            //客户端rule合法，则使用
            $data = $this->getVerifyData('mobile');
            $realRule = $data['rules']["$ruleAlias"];
        } elseif ($customRule = $this->retrieveMobileRule($token, $ruleAlias)) {
            //在服务器端存储过rule
            $this->sentInfo['verify']['mobile']['use'] = self::CUSTOM_RULE_FLAG;
            $realRule = $customRule;
        } else {
            //使用配置文件中默认rule
            $data = $this->getVerifyData('mobile');
            $ruleName = $data['use'];
            if ($this->hasRule('mobile', $ruleName)) {
                $realRule = $data['rules']["$ruleName"];
            }
        }

        return $realRule;
    }

    /**
     * get used rule`s alias
     *
     * @param $name
     *
     * @throws LaravelSmsException
     *
     * @return mixed
     */
    public function getUsedRuleAlias($name)
    {
        $data = $this->getVerifyData($name);

        return $data['use'];
    }

    /**
     * manual set verify rule
     *
     * @param $name
     * @param $value
     *
     * @return mixed
     */
    public function setUsedRuleAlias($name, $value)
    {
        if ($this->hasRule($name, $value)) {
            $this->sentInfo['verify']["$name"]['use'] = $value;

            return true;
        }

        return false;
    }

    /**
     * store custom mobile rule
     *
     * @param string|array $data
     *
     * @throws LaravelSmsException
     */
    public function storeMobileRule($data)
    {
        $token = $name = $rule = null;
        if (is_array($data)) {
            $token = isset($data['token']) ? $data['token'] : null;
            $name = isset($data['name']) ? $data['name'] : null;
            $rule = isset($data['rule']) ? $data['rule'] : null;
        } elseif (is_string($data)) {
            $rule = $data;
        }
        if (!$name) {
            $parsed = parse_url(URL::current());
            $name = $parsed['path'];
        }
        $key = $this->getStoreKey($token, self::CUSTOM_RULE_FLAG, $name);
        $this->storage()->set($key, $rule);
    }

    /**
     * retrieve custom mobile rule
     *
     * @param $token
     * @param string|null $name
     *
     * @throws LaravelSmsException
     *
     * @return mixed
     */
    public function retrieveMobileRule($token, $name = null)
    {
        if (!$name) {
            $parsed = parse_url(URL::previous());
            $realName = $parsed['path'];
        } else {
            $realName = $name;
        }
        $key = $this->getStoreKey($token, self::CUSTOM_RULE_FLAG, $realName);
        $customRule = $this->storage()->get($key, '');
        if ($name && !$customRule) {
            return $this->retrieveMobileRule($token, null);
        }

        return $customRule;
    }

    /**
     * forget custom mobile rule
     *
     * @param array $data
     *
     * @throws LaravelSmsException
     */
    public function forgetMobileRule(array $data = [])
    {
        $token = isset($data['token']) ? $data['token'] : null;
        $name = isset($data['name']) ? $data['name'] : null;
        $key = $this->getStoreKey($token, self::CUSTOM_RULE_FLAG, $name);
        $this->storage()->forget($key);
    }

    /**
     * whether to verify character data
     *
     * @param string $name
     *
     * @return mixed
     */
    public function isCheck($name = 'mobile')
    {
        $data = $this->getVerifyData($name);

        return (bool) $data['enable'];
    }

    /**
     * get verify sms templates id
     *
     * @return array
     */
    public function getVerifySmsTemplates()
    {
        $templates = [];
        $enableAgents = Sms::getEnableAgents();
        $agentsConfig = Sms::getAgentsConfig();
        foreach ($enableAgents as $name => $opts) {
            if (isset($agentsConfig["$name"])) {
                if (isset($agentsConfig["$name"]['verifySmsTemplateId'])) {
                    $templates[$name] = $agentsConfig["$name"]['verifySmsTemplateId'];
                }
            }
        }

        return $templates;
    }

    /**
     * get verify sms content
     *
     * @return mixed
     */
    public function getVerifySmsContent()
    {
        return config('laravel-sms.verifySmsContent');
    }

    /**
     * generate verify code
     *
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
     *
     * @return mixed
     */
    public function getCodeValidTime()
    {
        return config('laravel-sms.codeValidTime');
    }

    /**
     * 设置可以发送短信的时间
     *
     * @param int $token
     * @param int $seconds
     *
     * @return int
     */
    public function setResendTime($token, $seconds = 60)
    {
        $key = $this->getStoreKey($token, self::CAN_RESEND_UNTIL);
        $time = time() + $seconds;
        $this->storage()->set($key, $time);

        return $time;
    }

    /**
     * 获取可以发送短信的时间
     *
     * @param int $token
     *
     * @return mixed
     */
    protected function getCanSendTimeFromStorage($token = null)
    {
        $key = $this->getStoreKey($token, self::CAN_RESEND_UNTIL);

        return $this->storage()->get($key, 0);
    }

    /**
     * 判断能否发送
     *
     * @param  $token
     *
     * @return bool
     */
    public function canSend($token = null)
    {
        return $this->getCanSendTimeFromStorage($token) <= time();
    }

    /**
     * validator
     *
     * @param array  $input
     * @param string $rule
     *
     * @return array
     */
    public function validator(array $input, $rule = '')
    {
        if (!$input) {
            return $this->genResult(false, 'no_input_value');
        }
        $rule = $rule ?: (isset($input['rule']) ? $input['rule'] : '');
        $token = isset($input['token']) ? $input['token'] : null;
        if (!$this->canSend($token)) {
            $seconds = $input['seconds'];

            return $this->genResult(false, 'request_invalid', [$seconds]);
        }
        if ($this->isCheck('mobile')) {
            $realRule = $this->getRealMobileRule($rule, $token);
            $validator = Validator::make($input, [
                'mobile' => $realRule,
            ]);
            if ($validator->fails()) {
                $msg = $validator->errors()->first();
                $rule = $this->getUsedRuleAlias('mobile');

                return $this->genResult(false, $rule, $msg);
            }
        }

        return $this->genResult(true, 'success');
    }

    /**
     * generator validator result
     *
     * @param        $pass
     * @param        $type
     * @param string $message
     * @param array  $data
     *
     * @return array
     */
    public function genResult($pass, $type, $message = '', $data = [])
    {
        $result = [];
        $result['success'] = (bool) $pass;
        $result['type'] = $type;
        if (is_array($message)) {
            $data = $message;
            $message = '';
        }
        $message = $message ?: $this->getNotifyMessage($type);
        if ($message && is_array($data) && count($data)) {
            try {
                $message = vsprintf($message, $data);
            } catch (\Exception $e) {
            }
        }
        $result['message'] = $message;

        return $result;
    }

    /**
     * get notify message
     *
     * @param $name
     *
     * @return null
     */
    public function getNotifyMessage($name)
    {
        $messages = config('laravel-sms.notifies', []);
        if (array_key_exists($name, $messages)) {
            return $messages["$name"];
        }

        return $name;
    }
}
