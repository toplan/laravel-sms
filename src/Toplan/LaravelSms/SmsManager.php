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
        $verifyFields = self::getValidFields();
        $this->sentInfo = [
            'sent'          => false,
            'mobile'        => null,
            'code'          => null,
            'deadline_time' => 0,
            'verify'        => array_fill_keys($verifyFields, ''),
        ];
    }

    /**
     * 获取可验证的字段
     *
     * @return array
     */
    public static function getValidFields()
    {
        return array_keys(config('laravel-sms.verify', []));
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
        $className = self::getStorageClassName();
        if (!class_exists($className)) {
            throw new LaravelSmsException("Failed to generator store, the class [$className] is not exists.");
        }
        $store = new $className();
        if (!($store instanceof Storage)) {
            throw new LaravelSmsException("Failed to generator store, the class [$className] do not implement the interface [Toplan\\Sms\\Storage].");
        }

        return self::$store = $store;
    }

    /**
     * 获取存储器类名
     *
     * @return string
     */
    protected static function getStorageClassName()
    {
        $className = config('laravel-sms.storage', null);
        if ($className && is_string($className)) {
            return $className;
        }
        $middleware = config('laravel-sms.middleware', 'web');
        if ($middleware === 'web' || (is_array($middleware) && in_array('web', $middleware))) {
            return 'Toplan\Sms\SessionStorage';
        }
        if ($middleware === 'api' || (is_array($middleware) && in_array('api', $middleware))) {
            return 'Toplan\Sms\CacheStorage';
        }
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
        $args = array_filter(func_get_args(), function ($value) {
            return $value && is_string($value);
        });
        if (count($args)) {
            $prefix .= $split . implode($split, $args);
        }

        return $prefix;
    }

    /**
     * 验证数据
     *
     * @param array  $input
     *
     * @return array
     */
    public function validate(array $input)
    {
        if (empty($input)) {
            return self::genResult(false, 'no_input_value');
        }
        $token = isset($input['token']) ? $input['token'] : null;
        if (!self::sendable($token)) {
            $seconds = $input['seconds'];

            return self::genResult(false, 'request_invalid', [$seconds]);
        }
        $dataForValidator = [];
        foreach (self::getValidFields() as $field) {
            if (self::isCheck($field)) {
                $rule = isset($input[$field . 'Rule']) ? $input[$field . 'Rule'] : '';
                $dataForValidator[$field] = $this->getRealRule($field, $rule, $token);
            }
        }
        $validator = Validator::make($input, $dataForValidator);
        if ($validator->fails()) {
            $messages = $validator->errors();
            foreach (self::getValidFields() as $field) {
                if (!$messages->has($field)) {
                    continue;
                }
                $msg = $messages->first($field);
                $rule = $this->getUsedRule($field);

                return self::genResult(false, $rule, $msg);
            }
        }

        return self::genResult(true, 'success');
    }

    /**
     * 是否可发送短信/语音
     *
     * @param string|null $token
     *
     * @return bool
     */
    protected static function sendable($token)
    {
        $time = self::retrieveCanResendTime($token);

        return $time <= time();
    }

    /**
     * 是否检查指定的数据
     *
     * @param string $field
     *
     * @return bool
     */
    protected static function isCheck($field)
    {
        $data = self::getVerifyData($field);

        return (bool) $data['enable'];
    }

    /**
     * 根据规则别名获取真实的验证规则
     *
     * 首先尝试使用用户从客户端传递过来的rule
     * 其次尝试使用在服务器端存储过rule
     * 最后尝试使用配置文件中默认rule
     *
     * @param string      $field
     * @param string      $ruleName
     * @param string|null $token
     *
     * @return string
     */
    protected function getRealRule($field, $ruleName, $token = null)
    {
        $realRule = '';
        $data = self::getVerifyData($field);
        if ($this->useRule($field, $ruleName)) {
            $realRule = $data['rules'][$ruleName];
        } elseif ($customRule = self::retrieveRule($field, [
            'token' => $token,
            'name'  => $ruleName,
        ])) {
            $this->useRule($field, self::CUSTOM_RULE_KEY);
            $realRule = $customRule;
        } elseif ($this->useRule($field, self::getDefaultStaticRule($field))) {
            $realRule = $data['rules'][self::getDefaultStaticRule($field)];
        }

        return $realRule;
    }

    /**
     * 获取使用的规则的别名
     *
     * @param string $field
     *
     * @throws LaravelSmsException
     *
     * @return string
     */
    protected function getUsedRule($field)
    {
        return $this->sentInfo['verify'][$field];
    }

    /**
     * 通过规则名称设置指定数据使用的验证规则
     *
     * @param $field
     * @param $value
     *
     * @return mixed
     */
    protected function useRule($field, $value)
    {
        if (self::hasStaticRule($field, $value) || $value === self::CUSTOM_RULE_KEY) {
            $this->sentInfo['verify'][$field] = $value;

            return true;
        }

        return false;
    }

    /**
     * 是否含有指定字段的静态验证规则
     *
     * @param $field
     * @param $ruleName
     *
     * @return bool
     */
    protected static function hasStaticRule($field, $ruleName)
    {
        $data = self::getVerifyData($field);

        return isset($data['rules'][$ruleName]);
    }

    /**
     * 获取指定字段的默认静态规则的别名
     *
     * @param string $field
     *
     * @return string
     */
    protected static function getDefaultStaticRule($field)
    {
        $data = self::getVerifyData($field);

        return isset($data['default']) ? $data['default'] :
            (isset($data['use']) ? $data['use'] : '');
    }

    /**
     * 获取验证配置
     *
     * @param string $field
     *
     * @throws LaravelSmsException
     *
     * @return array
     */
    protected static function getVerifyData($field)
    {
        $data = config('laravel-sms.verify', []);
        if (isset($data["$field"])) {
            return $data["$field"];
        }
        throw new LaravelSmsException("Dont find verify data for field [$field] in config file.");
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
     * 设置可以发送的时间戳
     *
     * @param int $token
     * @param int $seconds
     *
     * @return int
     */
    public static function storeCanResendAfter($token, $seconds = 60)
    {
        $key = self::genKey($token, self::CAN_RESEND_UNTIL_KEY);
        $time = time() + $seconds;
        self::store()->set($key, $time);
    }

    /**
     * 从存储器中获取可发送时间戳
     *
     * @param string|null $token
     *
     * @return mixed
     * @throws LaravelSmsException
     */
    public static function retrieveCanResendTime($token)
    {
        $key = self::genKey($token, self::CAN_RESEND_UNTIL_KEY);

        return self::store()->get($key, 0);
    }

    /**
     * 存储手机号的自定义验证规则
     *
     * @param string       $field
     * @param string|array $data
     *
     * @throws LaravelSmsException
     */
    public static function storeRule($field, $data)
    {
        self::validateFieldName($field);
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
                throw new LaravelSmsException("Failed to store the custom mobile for field [$field], please set a name for custom rule.");
            }
        }
        $allRules = self::retrieveAllRule($field, $token);
        $allRules[$name] = (string) $rule;
        $key = self::genKey($token, self::CUSTOM_RULE_KEY, $field);
        self::store()->set($key, $allRules);
    }

    /**
     * 检查指定字段是否是可验证的
     *
     * @param $name
     *
     * @throws LaravelSmsException
     */
    protected static function validateFieldName($name)
    {
        if (!in_array($name, self::getValidFields())) {
            $names = implode(',', self::getValidFields());
            throw new LaravelSmsException("The field name [$name] is illegal, must be one of [$names].");
        }
    }

    /**
     * 获取存储的所有手机号验证规则
     *
     * @param string $field
     * @param mixed  $token
     *
     * @return mixed
     * @throws LaravelSmsException
     */
    public static function retrieveAllRule($field, $token = null)
    {
        if (is_array($token) && isset($token['token'])) {
            $token = $token['token'];
        }
        $key = self::genKey($token, self::CUSTOM_RULE_KEY, $field);

        return self::store()->get($key, []);
    }

    /**
     * 获取手机号的自定义验证规则
     *
     * @param string $field
     * @param array  $data
     *
     * @throws LaravelSmsException
     *
     * @return string
     */
    public static function retrieveRule($field, array $data = [])
    {
        $name = isset($data['name']) ? $data['name'] : null;
        if (!$name) {
            try {
                $parsed = parse_url(url()->previous());
                $realName = $parsed['path'];
            } catch (\Exception $e) {
                return '';
            }
        } else {
            $realName = $name;
        }
        $customRules = self::retrieveAllRule($field, $data);
        $customRule = isset($customRules[$realName]) ? $customRules[$realName] : '';
        if ($name && !$customRule) {
            $data['name'] = null;
            return self::retrieveRule($field, $data);
        }

        return $customRule;
    }

    /**
     * 删除手机号的自定义验证规则
     *
     * @param string $field
     * @param array  $data
     *
     * @throws LaravelSmsException
     */
    public static function forgetRule($field, array $data = [])
    {
        $token = isset($data['token']) ? $data['token'] : null;
        $name = isset($data['name']) ? $data['name'] : null;
        $allRules = self::retrieveAllRule($field, $data);
        if (!isset($allRules[$name])) {
            return;
        }
        unset($allRules[$name]);
        $key = self::genKey($token, self::CUSTOM_RULE_KEY, $field);
        self::store()->set($key, $allRules);
    }

    /**
     * 从存储器中获取所有数据
     *
     * @param string|null $token
     *
     * @throws LaravelSmsException
     *
     * @return array
     */
    public static function retrieveAll($token = null)
    {
        $data = [];
        $data[self::SMS_INFO_KEY] = self::retrieveSentInfo($token);
        $data[self::CAN_RESEND_UNTIL_KEY] = self::retrieveCanResendTime($token);
        $data[self::CUSTOM_RULE_KEY] = [];
        $fields = self::getValidFields();
        foreach ($fields as $field) {
            $data[self::CUSTOM_RULE_KEY][$field] = self::retrieveAllRule($field, $token);
        }

        return $data;
    }

    /**
     * 获取验证码短信的模版id
     *
     * @return array
     */
    public static function getSmsTemplates()
    {
        return self::getTemplatesByKey(self::VERIFY_SMS_TEMPLATE_KEY);
    }

    /**
     * 获取语音验证码的模版id
     *
     * @return array
     */
    public static function getVoiceTemplates()
    {
        return self::getTemplatesByKey(self::VOICE_VERIFY_TEMPLATE_KEY);
    }

    /**
     * 从配置信息中获取模版id
     *
     * @param string $key
     *
     * @return array
     */
    protected static function getTemplatesByKey($key)
    {
        $templates = [];
        $scheme = PhpSms::scheme();
        $config = PhpSms::config();
        foreach (array_keys($scheme) as $name) {
            if (isset($config[$name])) {
                if (isset($config[$name][$key])) {
                    $templates[$name] = $config[$name][$key];
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
}
