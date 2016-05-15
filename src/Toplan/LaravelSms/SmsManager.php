<?php

namespace Toplan\Sms;

use PhpSms;
use Validator;

class SmsManager
{
    const CUSTOM_RULE_FLAG = '_custom_rule_in_server';

    const CAN_RESEND_UNTIL = '_can_resend_until';

    const SMS_INFO_KEY = '_sms_info';

    /**
     * The information of sent sms
     *
     * @var array
     */
    protected $sentInfo = [];

    /**
     * The store
     *
     * @var Storage
     */
    protected static $store;

    /**
     * Constructor
     */
    public function __construct()
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
     * Get the information of sent sms
     *
     * @return array
     */
    public function getSentInfo()
    {
        return $this->sentInfo;
    }

    /**
     * Set the information of sent sms by key
     *
     * @param array|string $key
     * @param mixed        $data
     */
    protected function setSentInfo($key, $data = null)
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
     * Get the store
     *
     * @throws LaravelSmsException
     *
     * @return Storage
     */
    protected function store()
    {
        if (self::$store) {
            return self::$store;
        }
        $className = config('laravel-sms.storage', 'Toplan\Sms\SessionStorage');
        if (class_exists($className)) {
            self::$store = new $className();

            return self::$store;
        }
        throw new LaravelSmsException("Generator store failed, dont find class [$className].");
    }

    /**
     * Generate a key
     *
     * @return string
     */
    protected function genKey()
    {
        $split = '.';
        $prefix = config('laravel-sms.prefix', 'laravel_sms_info');
        $args = func_get_args();
        if (count($args)) {
            $args = array_filter($args, function ($value) {
                return $value && is_string($value);
            });
            $prefix .= $split . implode($split, $args);
        }

        return $prefix;
    }

    /**
     * Storage the information of sent sms to store
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
        $this->setSentInfo($data);
        $key = $this->genKey($token, self::SMS_INFO_KEY);
        $this->store()->set($key, $this->getSentInfo());
    }

    /**
     * Retrieve the information of sent sms from store
     *
     * @param  $token
     *
     * @return mixed
     */
    public function retrieveSentInfo($token = null)
    {
        $key = $this->genKey($token, self::SMS_INFO_KEY);

        return $this->store()->get($key, []);
    }

    /**
     * Forget the information of sent sms from store
     *
     * @param  $token
     */
    public function forgetSentInfo($token = null)
    {
        $key = $this->genKey($token, self::SMS_INFO_KEY);
        $this->store()->forget($key);
    }

    /**
     * Retrieve the information use of debug from store
     *
     * @param null $token
     *
     * @throws LaravelSmsException
     *
     * @return mixed
     */
    public function retrieveDebugInfo($token = null)
    {
        $key = $this->genKey($token);

        return $this->store()->get($key, []);
    }

    /**
     * 获取验证配置
     *
     * @param $name
     *
     * @throws LaravelSmsException
     *
     * @return array
     */
    protected function getVerifyData($name)
    {
        if (!$name) {
            return $this->sentInfo['verify'];
        }
        if ($this->sentInfo['verify']["$name"]) {
            return $this->sentInfo['verify']["$name"];
        }
        throw new LaravelSmsException("Dont find [$name] verify data in config file.");
    }

    /**
     * 是否含有指定的验证规则
     *
     * @param $name
     * @param $ruleName
     *
     * @return bool
     */
    protected function hasRule($name, $ruleName)
    {
        $data = $this->getVerifyData($name);

        return isset($data['rules']["$ruleName"]);
    }

    /**
     * 根据规则别名获取真实的验证规则
     *
     * @param $ruleAlias
     * @param $token
     *
     * @return string
     */
    protected function getRealMobileRule($ruleAlias = '', $token = null)
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
     * 获取使用的规则的别名
     *
     * @param $name
     *
     * @throws LaravelSmsException
     *
     * @return string
     */
    protected function getUsedRuleAlias($name)
    {
        $data = $this->getVerifyData($name);

        return $data['use'];
    }

    /**
     * 通过别名设置使用的验证规则
     *
     * @param $name
     * @param $value
     *
     * @return mixed
     */
    protected function setUsedRuleAlias($name, $value)
    {
        if ($this->hasRule($name, $value)) {
            $this->sentInfo['verify']["$name"]['use'] = $value;

            return true;
        }

        return false;
    }

    /**
     * 存储手机号的自定义验证规则
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
            try {
                $parsed = parse_url(url()->current());
                $name = $parsed['path'];
            } catch (\Exception $e) {
                throw new LaravelSmsException('Store the custom mobile failed, please set a name for custom rule.');
            }
        }
        $key = $this->genKey($token, self::CUSTOM_RULE_FLAG, $name);
        $this->store()->set($key, $rule);
    }

    /**
     * 获取手机号的自定义验证规则
     *
     * @param $token
     * @param string|null $name
     *
     * @throws LaravelSmsException
     *
     * @return string
     */
    public function retrieveMobileRule($token, $name = null)
    {
        if (!$name) {
            try {
                $parsed = parse_url(url()->previous());
                $realName = $parsed['path'];
            } catch (\Exception $e) {
                return;
            }
        } else {
            $realName = $name;
        }
        $key = $this->genKey($token, self::CUSTOM_RULE_FLAG, $realName);
        $customRule = $this->store()->get($key, '');
        if ($name && !$customRule) {
            return $this->retrieveMobileRule($token, null);
        }

        return $customRule;
    }

    /**
     * 删除手机号的自定义验证规则
     *
     * @param array $data
     *
     * @throws LaravelSmsException
     */
    public function forgetMobileRule(array $data = [])
    {
        $token = isset($data['token']) ? $data['token'] : null;
        $name = isset($data['name']) ? $data['name'] : null;
        $key = $this->genKey($token, self::CUSTOM_RULE_FLAG, $name);
        $this->store()->forget($key);
    }

    /**
     * 是否检查指定的项目
     *
     * @param string $name
     *
     * @return bool
     */
    protected function isCheck($name = 'mobile')
    {
        $data = $this->getVerifyData($name);

        return (bool) $data['enable'];
    }

    /**
     * 从配置文件获取模版id
     *
     * @return array
     */
    public function getVerifySmsTemplates()
    {
        $templates = [];
        $scheme = PhpSms::scheme();
        $config = PhpSms::config();
        foreach (array_keys($scheme) as $name) {
            if (isset($config["$name"])) {
                if (isset($config["$name"]['verifySmsTemplateId'])) {
                    $templates[$name] = $config["$name"]['verifySmsTemplateId'];
                }
            }
        }

        return $templates;
    }

    /**
     * 从配置文件获取短信内容
     *
     * @return string
     */
    public function getVerifySmsContent()
    {
        return config('laravel-sms.verifySmsContent');
    }

    /**
     * 根据配置文件中的长度生成验证码
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
     * 从配置文件获取验证码有效时间(分钟)
     *
     * @return int
     */
    public function getCodeValidTime()
    {
        return config('laravel-sms.codeValidTime', 5);
    }

    /**
     * 设置可以发送短信的时间
     *
     * @param int $token
     * @param int $seconds
     *
     * @return int
     */
    public function storeCanResendUntil($token, $seconds = 60)
    {
        $key = $this->genKey($token, self::CAN_RESEND_UNTIL);
        $time = time() + $seconds;
        $this->store()->set($key, $time);
    }

    /**
     * 验证数据
     *
     * @param array  $input
     * @param string $rule
     *
     * @return array
     */
    public function validate(array $input, $rule = '')
    {
        if (!$input) {
            return $this->genResult(false, 'no_input_value');
        }
        $rule = $rule ?: (isset($input['rule']) ? $input['rule'] : '');
        $token = isset($input['token']) ? $input['token'] : null;
        if (!$this->sendable($token)) {
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
     * 是否可发送短信/语音
     *
     * @param  $token
     *
     * @return bool
     */
    protected function sendable($token = null)
    {
        $key = $this->genKey($token, self::CAN_RESEND_UNTIL);
        $time = $this->store()->get($key, 0);

        return $time <= time();
    }

    /**
     * 合成结果数组
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
        $result['message'] = $this->vsprintf($message, $data);

        return $result;
    }

    /**
     * 根据模版和数据合成字符串
     *
     * @param string $template
     * @param array  $data
     *
     * @return string
     */
    public function vsprintf($template, array $data)
    {
        if (!is_string($template)) {
            return '';
        }
        if ($template && count($data)) {
            try {
                $template = vsprintf($template, $data);
            } catch (\Exception $e) {
                // swallow exception
            }
        }

        return $template;
    }

    /**
     * 从配置文件获取提示信息
     *
     * @param $name
     *
     * @return string
     */
    protected function getNotifyMessage($name)
    {
        $messages = config('laravel-sms.notifies', []);
        if (array_key_exists($name, $messages)) {
            return $messages["$name"];
        }

        return $name;
    }
}
