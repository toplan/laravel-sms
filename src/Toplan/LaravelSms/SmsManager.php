<?php

namespace Toplan\Sms;

use PhpSms;
use Validator;

class SmsManager
{
    const CUSTOM_RULE_KEY = '_custom_rule_in_server';

    const CAN_RESEND_UNTIL_KEY = '_can_resend_until';

    const SMS_INFO_KEY = '_sms_info';

    const VERIFY_SMS_TEMPLATE_KEY = 'verifySmsTemplateId';

    const VOICE_VERIFY_TEMPLATE_KEY = 'voiceVerifyTemplateId';

    /**
     * The store
     *
     * @var Storage
     */
    protected static $store;

    /**
     * The information of sent sms
     *
     * @var array
     */
    protected $sentInfo = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $verifyFields = array_keys(config('laravel-sms.verify', []));
        $this->sentInfo = [
            'sent'          => false,
            'mobile'        => null,
            'code'          => null,
            'deadline_time' => 0,
            'verify'        => array_fill_keys($verifyFields, ''),
        ];
    }

    /**
     * 获取发送相关信息
     *
     * @return array
     */
    public function getSentInfo()
    {
        return $this->sentInfo;
    }

    /**
     * 获取存储器
     *
     * @throws LaravelSmsException
     *
     * @return Storage
     */
    protected static function store()
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
    protected static function genKey()
    {
        $split = '.';
        $prefix = config('laravel-sms.prefix', 'laravel_sms');
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
     * 存储发送相关信息
     *
     * @param       $token
     * @param array $data
     *
     * @throws LaravelSmsException
     */
    public static function storeSentInfo($token, $data = [])
    {
        if (is_array($token)) {
            $data = $token;
            $token = null;
        }
        $key = self::genKey($token, self::SMS_INFO_KEY);
        self::store()->set($key, $data);
    }

    /**
     * 从存储器中获取发送相关信息
     *
     * @param string|null $token
     *
     * @return mixed
     */
    public static function retrieveSentInfo($token = null)
    {
        $key = self::genKey($token, self::SMS_INFO_KEY);

        return self::store()->get($key, []);
    }

    /**
     * 从存储器中删除发送相关的信息
     *
     * @param string|null $token
     */
    public static function forgetSentInfo($token = null)
    {
        $key = self::genKey($token, self::SMS_INFO_KEY);
        self::store()->forget($key);
    }

    /**
     * 从存储器中获取数据用于调试
     *
     * @param string|null $token
     *
     * @throws LaravelSmsException
     *
     * @return mixed
     */
    public static function retrieveDebugInfo($token = null)
    {
        return self::store()->get(self::genKey($token), []);
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
            return self::genResult(false, 'no_input_value');
        }
        $rule = $rule ?: (isset($input['rule']) ? $input['rule'] : '');
        $token = isset($input['token']) ? $input['token'] : null;
        if (!self::sendable($token)) {
            $seconds = $input['seconds'];

            return self::genResult(false, 'request_invalid', [$seconds]);
        }
        if (self::isCheck('mobile')) {
            $realRule = $this->getRealMobileRule($rule, $token);
            $validator = Validator::make($input, [
                'mobile' => $realRule,
            ]);
            if ($validator->fails()) {
                $msg = $validator->errors()->first();
                $rule = $this->getUsedRuleAlias('mobile');

                return self::genResult(false, $rule, $msg);
            }
        }

        return self::genResult(true, 'success');
    }

    /**
     * 是否可发送短信/语音
     *
     * @param  $token
     *
     * @return bool
     */
    protected static function sendable($token = null)
    {
        $key = self::genKey($token, self::CAN_RESEND_UNTIL_KEY);
        $time = self::store()->get($key, 0);

        return $time <= time();
    }

    /**
     * 是否检查指定的项目
     *
     * @param string $name
     *
     * @return bool
     */
    protected static function isCheck($name = 'mobile')
    {
        $data = self::getVerifyData($name);

        return (bool) $data['enable'];
    }

    /**
     * 根据规则别名获取真实的验证规则
     *
     * @param string      $ruleAlias
     * @param string|null $token
     *
     * @return string
     */
    protected function getRealMobileRule($ruleAlias = '', $token = null)
    {
        $realRule = '';
        $data = self::getVerifyData('mobile');
        //尝试使用用户从客户端传递过来的rule
        if ($this->setUsedRuleAlias('mobile', $ruleAlias)) {
            //客户端rule合法，则使用
            $realRule = $data['rules']["$ruleAlias"];
        } elseif ($customRule = self::retrieveMobileRule($token, $ruleAlias)) {
            //在服务器端存储过rule
            $this->setUsedRuleAlias('mobile', self::CUSTOM_RULE_KEY);
            $realRule = $customRule;
        } elseif ($this->setUsedRuleAlias('mobile', self::getDefaultStaticRuleAlias('mobile'))) {
            //使用配置文件中默认rule
            $realRule = $data['rules'][self::getDefaultStaticRuleAlias('mobile')];
        }

        return $realRule;
    }

    /**
     * 获取使用的规则的别名
     *
     * @param string $name
     *
     * @throws LaravelSmsException
     *
     * @return string
     */
    protected function getUsedRuleAlias($name)
    {
        return $this->sentInfo['verify']["$name"];
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
        if (self::hasStaticRule($name, $value) || $value === self::CUSTOM_RULE_KEY) {
            $this->sentInfo['verify']["$name"] = $value;

            return true;
        }

        return false;
    }

    /**
     * 是否含有指定字段的静态验证规则
     *
     * @param $name
     * @param $ruleName
     *
     * @return bool
     */
    protected static function hasStaticRule($name, $ruleName)
    {
        $data = self::getVerifyData($name);

        return isset($data['rules']["$ruleName"]);
    }

    /**
     * 获取指定字段的默认静态规则的别名
     *
     * @param string $name
     *
     * @return string
     */
    protected static function getDefaultStaticRuleAlias($name)
    {
        $data = self::getVerifyData($name);

        return isset($data['default']) ? $data['default'] :
            (isset($data['use']) ? $data['use'] : '');
    }

    /**
     * 获取验证配置
     *
     * @param string $name
     *
     * @throws LaravelSmsException
     *
     * @return array
     */
    protected static function getVerifyData($name)
    {
        $data = config('laravel-sms.verify', []);
        if (isset($data["$name"])) {
            return $data["$name"];
        }
        throw new LaravelSmsException("Dont find verify data for field [$name] in config file.");
    }

    /**
     * 存储手机号的自定义验证规则
     *
     * @param string|array $data
     *
     * @throws LaravelSmsException
     */
    public static function storeMobileRule($data)
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
        $key = self::genKey($token, self::CUSTOM_RULE_KEY, $name);
        self::store()->set($key, $rule);
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
    public static function retrieveMobileRule($token, $name = null)
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
        $key = self::genKey($token, self::CUSTOM_RULE_KEY, $realName);
        $customRule = self::store()->get($key, '');
        if ($name && !$customRule) {
            return self::retrieveMobileRule($token, null);
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
    public static function forgetMobileRule(array $data = [])
    {
        $token = isset($data['token']) ? $data['token'] : null;
        $name = isset($data['name']) ? $data['name'] : null;
        $key = self::genKey($token, self::CUSTOM_RULE_KEY, $name);
        self::store()->forget($key);
    }

    /**
     * 从配置信息中获取模版id
     *
     * @param string $key
     *
     * @return array
     */
    public static function getTemplatesByKey($key = self::VERIFY_SMS_TEMPLATE_KEY)
    {
        $templates = [];
        $scheme = PhpSms::scheme();
        $config = PhpSms::config();
        foreach (array_keys($scheme) as $name) {
            if (isset($config["$name"])) {
                if (isset($config["$name"]["$key"])) {
                    $templates[$name] = $config["$name"]["$key"];
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
    public static function getVerifySmsContent()
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
    public static function generateCode($length = null, $characters = null)
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
    public static function getCodeValidTime()
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
    public static function storeCanResendUntil($token, $seconds = 60)
    {
        $key = self::genKey($token, self::CAN_RESEND_UNTIL_KEY);
        $time = time() + $seconds;
        self::store()->set($key, $time);
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
    public static function genResult($pass, $type, $message = '', $data = [])
    {
        $result = [];
        $result['success'] = (bool) $pass;
        $result['type'] = $type;
        if (is_array($message)) {
            $data = $message;
            $message = '';
        }
        $message = $message ?: self::getNotifyMessage($type);
        $result['message'] = self::vsprintf($message, $data);

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
    public static function vsprintf($template, array $data)
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
    protected static function getNotifyMessage($name)
    {
        $messages = config('laravel-sms.notifies', []);
        if (array_key_exists($name, $messages)) {
            return $messages["$name"];
        }

        return $name;
    }

    /**
     * Properties override
     *
     * @param $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        if (isset($this->$name)) {
            return $this->$name;
        }
    }
}
