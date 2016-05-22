<?php

namespace Toplan\Sms;

use PhpSms;
use Validator;

class SmsManager
{
    const VERSION = '2.4.0';

    const STATE_KEY = '_state';

    const DYNAMIC_RULE_KEY = '_dynamic_rule';

    const CAN_RESEND_UNTIL_KEY = '_can_resend_until';

    const VERIFY_SMS_TEMPLATE_KEY = 'verifySmsTemplateId';

    const VOICE_VERIFY_TEMPLATE_KEY = 'voiceVerifyTemplateId';

    /**
     * 存储器
     *
     * @var Storage
     */
    protected static $storage;

    /**
     * Access Token
     *
     * @var string|null
     */
    protected $token = null;

    /**
     * 发送状态
     *
     * @var array
     */
    protected $state = [];

    /**
     * Constructor
     *
     * @param string|null $token
     */
    public function __construct($token = null)
    {
        $this->reset();
        if ($token && is_string($token)) {
            $this->token = $token;
        }
    }

    /**
     * 重置发送状态
     */
    protected function reset()
    {
        $fields = self::getFields();
        $this->state = [
            'sent'     => false,
            'to'       => null,
            'code'     => null,
            'deadline' => 0,
            'usedRule' => array_fill_keys($fields, null),
        ];
    }

    /**
     * 是否可发送短信/语音
     *
     * @param int $interval
     *
     * @return bool
     */
    public function validateSendable($interval)
    {
        $interval = intval($interval);
        $time = $this->getCanResendTime();
        if ($time <= time()) {
            return self::generateResult(true, 'can_send');
        }

        return self::generateResult(false, 'request_invalid', [$interval]);
    }

    /**
     * 验证数据
     *
     * @param array $data
     *
     * @return array
     */
    public function validateFields(array $data)
    {
        if (empty($data)) {
            return self::generateResult(false, 'empty_data');
        }

        $dataForValidator = [];
        $fields = self::getFields();
        foreach ($fields as $field) {
            if (self::whetherValidateFiled($field)) {
                $ruleName = isset($data[$field . '_rule']) ? $data[$field . '_rule'] : '';
                $dataForValidator[$field] = $this->getRealRuleByName($field, $ruleName);
            }
        }
        $validator = Validator::make($data, $dataForValidator);

        if ($validator->fails()) {
            $messages = $validator->errors();
            foreach ($fields as $field) {
                if ($messages->has($field)) {
                    $rule = $this->getNameOfUsedRule($field);
                    $msg = $messages->first($field);

                    return self::generateResult(false, $rule, $msg);
                }
            }
        }

        return self::generateResult(true, 'success');
    }

    /**
     * 根据规则名获取真实的验证规则
     *
     * 首先尝试使用用户从客户端传递过来的rule
     * 其次尝试使用在服务器端存储过rule
     * 最后尝试使用配置文件中默认rule
     *
     * @param string $field
     * @param string $ruleName
     *
     * @return string
     */
    protected function getRealRuleByName($field, $ruleName)
    {
        if (empty($ruleName) || !is_string($ruleName)) {
            try {
                $parsed = parse_url(url()->previous());
                $ruleName = $parsed['path'];
            } catch (\Exception $e) {
                $ruleName = '';
            }
        }
        if ($staticRule = $this->getStaticRule($field, $ruleName)) {
            $this->useRule($field, $ruleName);

            return $staticRule;
        }
        if ($dynamicRule = $this->retrieveRule($field, $ruleName)) {
            $this->useRule($field, $ruleName);

            return $dynamicRule;
        }
        $default = self::getNameOfDefaultStaticRule($field);
        if ($staticRule = $this->getStaticRule($field, $default)) {
            $this->useRule($field, $default);

            return $staticRule;
        }

        return '';
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
    protected function getNameOfUsedRule($field)
    {
        return isset($this->state['usedRule'][$field]) ? $this->state['usedRule'][$field] : '';
    }

    /**
     * 通过规则名称设置指定数据使用的验证规则
     *
     * @param string $field
     * @param string $name
     */
    protected function useRule($field, $name)
    {
        $this->state['usedRule'][$field] = $name;
    }

    /**
     * 请求验证码短信
     *
     * @param string $for
     * @param int    $interval
     *
     * @return array
     */
    public function requestVerifySms($for, $interval)
    {
        $code = self::generateCode();
        $minutes = self::getCodeValidMinutes();
        $templates = self::getTemplatesByKey(self::VERIFY_SMS_TEMPLATE_KEY);
        $content = self::generateSmsContent([$code, $minutes]);
        $result = PhpSms::make($templates)->to($for)
            ->data(['code' => $code, 'minutes' => $minutes])
            ->content($content)->send();

        if ($result === null || $result === true || (isset($result['success']) && $result['success'])) {
            $this->state['sent'] = true;
            $this->state['to'] = $for;
            $this->state['code'] = $code;
            $this->state['deadline'] = time() + ($minutes * 60);
            $this->storeState();
            $this->setCanResendAfter($interval);

            return self::generateResult(true, 'sms_sent_success');
        }

        return self::generateResult(false, 'sms_sent_failure');
    }

    /**
     * 请求语音验证码
     *
     * @param string $for
     * @param int    $interval
     *
     * @return array
     */
    public function requestVoiceVerify($for, $interval)
    {
        $code = self::generateCode();
        $minutes = self::getCodeValidMinutes();
        $templates = self::getTemplatesByKey(self::VOICE_VERIFY_TEMPLATE_KEY);
        $result = PhpSms::voice($code)->template($templates)
            ->data(['code' => $code])->to($for)->send();

        if ($result === null || $result === true || (isset($result['success']) && $result['success'])) {
            $this->state['sent'] = true;
            $this->state['to'] = $for;
            $this->state['code'] = $code;
            $this->state['deadline'] = time() + ($minutes * 60);
            $this->storeState();
            $this->setCanResendAfter($interval);

            return self::generateResult(true, 'voice_sent_success');
        }

        return self::generateResult(false, 'voice_sent_failure');
    }

    /**
     * 存储发送状态
     *
     * @throws LaravelSmsException
     */
    public function storeState()
    {
        $key = self::generateKey(self::STATE_KEY);
        self::storage()->set($key, $this->state);
        $this->reset();
    }

    /**
     * 从存储器中获取发送状态
     *
     * @return array
     */
    public function retrieveState()
    {
        $key = self::generateKey(self::STATE_KEY);

        return self::storage()->get($key, []);
    }

    /**
     * 从存储器中删除发送状态
     */
    public function forgetState()
    {
        $key = self::generateKey(self::STATE_KEY);
        self::storage()->forget($key);
    }

    /**
     * 设置多少秒后才能再次请求
     *
     * @param int $interval
     *
     * @return int
     */
    public function setCanResendAfter($interval)
    {
        $key = self::generateKey(self::CAN_RESEND_UNTIL_KEY);
        $time = time() + intval($interval);
        self::storage()->set($key, $time);
    }

    /**
     * 从存储器中获取可再次发送的截止时间
     *
     * @return int
     */
    public function getCanResendTime()
    {
        $key = $this->generateKey(self::CAN_RESEND_UNTIL_KEY);

        return (int) self::storage()->get($key, 0);
    }

    /**
     * 存储指定字段的指定名称的动态验证规则
     *
     * @param string      $field
     * @param string      $name
     * @param string|null $rule
     *
     * @throws LaravelSmsException
     */
    public function storeRule($field, $name, $rule = null)
    {
        self::validateFieldName($field);
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                $this->storeRule($field, $k, $v);
            }

            return;
        }
        if ($rule === null && is_string($name)) {
            $rule = $name;
            $name = null;
        }
        if (empty($name) || !is_string($name)) {
            try {
                $parsed = parse_url(url()->current());
                $name = $parsed['path'];
            } catch (\Exception $e) {
                throw new LaravelSmsException("Failed to store the custom mobile for field [$field], please set a name for custom rule.");
            }
        }
        $allRules = $this->retrieveRules($field);
        $allRules[$name] = $rule;
        $key = self::generateKey(self::DYNAMIC_RULE_KEY, $field);
        self::storage()->set($key, $allRules);
    }

    /**
     * 从存储中获取指定字段的所有验证规则
     *
     * @param string $field
     *
     * @return array
     */
    public function retrieveRules($field)
    {
        $key = self::generateKey(self::DYNAMIC_RULE_KEY, $field);

        return self::storage()->get($key, []);
    }

    /**
     * 从存储器中获取指定字段的指定名称的动态验证规则
     *
     * @param string $field
     * @param string $name
     *
     * @return string
     */
    public function retrieveRule($field, $name)
    {
        $allRules = $this->retrieveRules($field);

        return isset($allRules[$name]) ? $allRules[$name] : '';
    }

    /**
     * 从存储器中删除指定字段的指定名称的动态验证规则
     *
     * @param string $field
     * @param string $name
     *
     * @throws LaravelSmsException
     */
    public function forgetRule($field, $name)
    {
        $allRules = $this->retrieveRules($field);
        if (!isset($allRules[$name])) {
            return;
        }
        unset($allRules[$name]);
        $key = self::generateKey(self::DYNAMIC_RULE_KEY, $field);
        self::storage()->set($key, $allRules);
    }

    /**
     * 从存储器中获取用户的所有数据
     *
     * @return array
     */
    public function retrieveAllData()
    {
        $data = [];
        $data[self::STATE_KEY] = $this->retrieveState();
        $data[self::CAN_RESEND_UNTIL_KEY] = $this->getCanResendTime();
        $data[self::DYNAMIC_RULE_KEY] = [];
        $fields = self::getFields();
        foreach ($fields as $field) {
            $data[self::DYNAMIC_RULE_KEY][$field] = $this->retrieveRules($field);
        }

        return $data;
    }

    /**
     * 生成key
     *
     * @return string
     */
    protected function generateKey()
    {
        $split = '.';
        $prefix = config('laravel-sms.prefix', 'laravel_sms');
        $args = func_get_args();
        array_unshift($args, $this->token);
        $args = array_filter($args, function ($value) {
            return $value && is_string($value);
        });
        if (count($args)) {
            $prefix .= $split . implode($split, $args);
        }

        return $prefix;
    }

    /**
     * 获取可验证的字段
     *
     * @return array
     */
    protected static function getFields()
    {
        return array_keys(config('laravel-sms.validation', []));
    }

    /**
     * 获取存储器
     *
     * @throws LaravelSmsException
     *
     * @return Storage
     */
    protected static function storage()
    {
        if (self::$storage) {
            return self::$storage;
        }
        $className = self::getStorageClassName();
        if (!class_exists($className)) {
            throw new LaravelSmsException("Failed to generator store, the class [$className] does not exists.");
        }
        $store = new $className();
        if (!($store instanceof Storage)) {
            throw new LaravelSmsException("Failed to generator store, the class [$className] does not implement the interface [Toplan\\Sms\\Storage].");
        }

        return self::$storage = $store;
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
     * 是否检查指定的数据
     *
     * @param string $field
     *
     * @return bool
     */
    protected static function whetherValidateFiled($field)
    {
        $data = self::getValidationConfigByField($field);

        return isset($data['enable']) && $data['enable'];
    }

    /**
     * 获取指定字段的指定名称的静态验证规则
     *
     * @param $field
     * @param $ruleName
     *
     * @return string
     */
    protected static function getStaticRule($field, $ruleName)
    {
        $data = self::getValidationConfigByField($field);

        return isset($data['staticRules'][$ruleName]) ? $data['staticRules'][$ruleName] : '';
    }

    /**
     * 获取指定字段的默认静态规则的名称
     *
     * @param string $field
     *
     * @return string
     */
    protected static function getNameOfDefaultStaticRule($field)
    {
        $data = self::getValidationConfigByField($field);

        return isset($data['default']) ? $data['default'] : '';
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
    protected static function getValidationConfigByField($field)
    {
        $data = config('laravel-sms.validation', []);
        if (isset($data[$field])) {
            return $data[$field];
        }
        throw new LaravelSmsException("Don't find validation config for the field [$field] in config file, please define it.");
    }

    /**
     * 检查字段名称是否合法
     *
     * @param $name
     *
     * @throws LaravelSmsException
     */
    protected static function validateFieldName($name)
    {
        $fields = self::getFields();
        if (!in_array($name, $fields)) {
            $names = implode(',', $fields);
            throw new LaravelSmsException("The field name [$name] is illegal, must be one of [$names].");
        }
    }

    /**
     * 从配置信息中获取指定键名的所有模版id
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
     * 生成验证码短信通用内容
     *
     * @param array $data
     *
     * @return string
     */
    protected static function generateSmsContent(array $data = [])
    {
        return self::vsprintf(config('laravel-sms.verifySmsContent'), $data);
    }

    /**
     * 根据配置文件中的长度生成验证码
     *
     * @param int|null    $length
     * @param string|null $characters
     *
     * @return string
     */
    protected static function generateCode($length = null, $characters = null)
    {
        $length = (int) ($length ?: config('laravel-sms.codeLength', 5));
        $characters = (string) ($characters ?: '0123456789');
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
    protected static function getCodeValidMinutes()
    {
        return (int) config('laravel-sms.codeValidMinutes', 5);
    }

    /**
     * 合成结果数组
     *
     * @param bool   $pass
     * @param string $type
     * @param string $message
     * @param array  $data
     *
     * @return array
     */
    protected static function generateResult($pass, $type, $message = '', $data = [])
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
    protected static function vsprintf($template, array $data)
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
     * @param string $name
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
