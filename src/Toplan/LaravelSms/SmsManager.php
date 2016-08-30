<?php

namespace Toplan\Sms;

use PhpSms;
use URL;
use Validator;

class SmsManager
{
    const VERSION = '2.5.1';

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
     * 客户端数据
     *
     * @var array
     */
    protected $input = [];

    /**
     * Constructor
     *
     * @param string|null $token
     * @param array       $input
     */
    public function __construct($token = null, array $input = [])
    {
        if ($token && is_string($token)) {
            $this->token = $token;
        }
        $this->input = array_merge($this->input, $input);
        $this->reset();
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
            'attempts' => 0,
        ];
    }

    /**
     * 验证是否可发送
     *
     * @return array
     */
    public function validateSendable()
    {
        $time = $this->getCanResendTime();
        if ($time <= time()) {
            return self::generateResult(true, 'can_send');
        }

        return self::generateResult(false, 'request_invalid', [self::getInterval()]);
    }

    /**
     * 验证数据
     *
     * @param mixed         $input
     * @param \Closure|null $validation
     *
     * @return array
     */
    public function validateFields($input = null, \Closure $validation = null)
    {
        if (is_callable($input)) {
            $validation = $input;
            $input = null;
        }
        if (is_array($input)) {
            $this->input = array_merge($this->input, $input);
        }

        $rules = [];
        $fields = self::getFields();
        foreach ($fields as $field) {
            if (self::whetherValidateFiled($field)) {
                $ruleName = $this->input($field . '_rule');
                $rules[$field] = $this->getRealRuleByName($field, $ruleName);
            }
        }

        if ($validation) {
            return call_user_func_array($validation, [$this->input(), $rules]);
        }

        if (empty($this->input())) {
            return self::generateResult(false, 'empty_data');
        }

        $validator = Validator::make($this->input(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors();
            foreach ($fields as $field) {
                if ($messages->has($field)) {
                    $rule = $this->usedRule($field);
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
     * - 首先尝试使用指定名称的静态验证规则
     * - 其次尝试使用指定名称的动态验证规则
     * - 最后尝试使用配置文件中的默认静态验证规则
     *
     * @param string $field
     * @param string $ruleName
     *
     * @return string
     */
    protected function getRealRuleByName($field, $ruleName)
    {
        if (empty($ruleName) || !is_string($ruleName)) {
            $ruleName = self::pathOfUrl(URL::previous());
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
     * 设置指定字段使用的验证规则名称
     *
     * @param string $field
     * @param string $name
     */
    protected function useRule($field, $name)
    {
        $this->state['usedRule'][$field] = $name;
    }

    /**
     * 获取设置指定字段使用的验证规则名称
     *
     * @param string $field
     *
     * @return string
     */
    protected function usedRule($field)
    {
        return isset($this->state['usedRule'][$field]) ? $this->state['usedRule'][$field] : '';
    }

    /**
     * 生成待发生的验证码
     *
     * @return string
     */
    protected function verifyCode()
    {
        if (config('laravel-sms.verifyCode.repeatIfValid', false)) {
            $state = $this->retrieveState();
            //如果在未来60秒内都还有效，那么重复使用该验证码
            if (!(empty($state)) && $state['deadline'] >= time() + 60) {
                return $state['code'];
            }
        }

        return self::generateCode();
    }

    /**
     * 请求验证码短信
     *
     * @return array
     */
    public function requestVerifySms()
    {
        $minutes = self::getCodeValidMinutes();
        $templates = self::getTemplatesByKey(self::VERIFY_SMS_TEMPLATE_KEY);

        $code = $this->verifyCode();
        $for = $this->input(self::getMobileField());

        $content = $this->generateSmsContent($code, $minutes);
        $tplData = $this->generateTemplateData($code, $minutes, 'verify_sms');

        $result = PhpSms::make($templates)->to($for)
            ->data($tplData)->content($content)->send();

        if ($result === null || $result === true || (isset($result['success']) && $result['success'])) {
            $this->state['sent'] = true;
            $this->state['to'] = $for;
            $this->state['code'] = $code;
            $this->state['deadline'] = time() + ($minutes * 60);
            $this->storeState();
            $this->setCanResendAfter(self::getInterval());

            return self::generateResult(true, 'sms_sent_success');
        }

        return self::generateResult(false, 'sms_sent_failure');
    }

    /**
     * 请求语音验证码
     *
     * @return array
     */
    public function requestVoiceVerify()
    {
        $minutes = self::getCodeValidMinutes();
        $templates = self::getTemplatesByKey(self::VOICE_VERIFY_TEMPLATE_KEY);

        $code = $this->verifyCode();
        $for = $this->input(self::getMobileField());

        $tplData = $this->generateTemplateData($code, $minutes, 'voice_verify');
        $result = PhpSms::voice($code)->template($templates)
            ->data($tplData)->to($for)->send();

        if ($result === null || $result === true || (isset($result['success']) && $result['success'])) {
            $this->state['sent'] = true;
            $this->state['to'] = $for;
            $this->state['code'] = $code;
            $this->state['deadline'] = time() + ($minutes * 60);
            $this->storeState();
            $this->setCanResendAfter(self::getInterval());

            return self::generateResult(true, 'voice_sent_success');
        }

        return self::generateResult(false, 'voice_sent_failure');
    }

    /**
     * 获取当前的发送状态(非持久化的)
     *
     * @param string|int|null $key
     * @param mixed           $default
     *
     * @return mixed
     */
    public function state($key = null, $default = null)
    {
        if ($key !== null) {
            return isset($this->state[$key]) ? $this->state[$key] : $default;
        }

        return $this->state;
    }

    /**
     * 获取客户端数据
     *
     * @param string|int|null $key
     * @param mixed           $default
     *
     * @return mixed
     */
    public function input($key = null, $default = null)
    {
        if ($key !== null) {
            return isset($this->input[$key]) ? $this->input[$key] : $default;
        }

        return $this->input;
    }

    /**
     * 存储发送状态
     */
    protected function storeState()
    {
        $this->updateState($this->state);
        $this->reset();
    }

    /**
     * 更新发送状态
     *
     * @param string|array $name
     * @param mixed        $value
     */
    public function updateState($name, $value = null)
    {
        $state = $this->retrieveState();
        if (is_array($name)) {
            $state = array_merge($state, $name);
        } elseif (is_string($name)) {
            $state[$name] = $value;
        }
        $key = self::generateKey(self::STATE_KEY);
        self::storage()->set($key, $state);
    }

    /**
     * 从存储器中获取发送状态
     *
     * @param string|null $name
     *
     * @return array
     */
    public function retrieveState($name = null)
    {
        $key = self::generateKey(self::STATE_KEY);
        $state = self::storage()->get($key, []);
        if ($name !== null) {
            return isset($state[$name]) ? $state[$name] : null;
        }

        return $state;
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
            $name = self::pathOfUrl(URL::current(), function ($e) use ($field) {
                throw new LaravelSmsException("Failed to store the dynamic rule for [$field] field, please give a name for it.");
            });
        }
        $allRules = $this->retrieveRules($field);
        $allRules[$name] = $rule;
        $key = self::generateKey(self::DYNAMIC_RULE_KEY, $field);
        self::storage()->set($key, $allRules);
    }

    /**
     * 从存储器中获取指定字段的指定名称的动态验证规则
     *
     * @param string      $field
     * @param string|null $name
     *
     * @return string|null
     */
    public function retrieveRule($field, $name = null)
    {
        $key = self::generateKey(self::DYNAMIC_RULE_KEY, $field);
        $allRules = self::storage()->get($key, []);
        if (empty($name)) {
            return $allRules;
        }

        return isset($allRules[$name]) ? $allRules[$name] : null;
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
        return $this->retrieveRule($field);
    }

    /**
     * 从存储器中删除指定字段的指定名称的动态验证规则
     *
     * @param string      $field
     * @param string|null $name
     */
    public function forgetRule($field, $name = null)
    {
        $allRules = [];
        if (!(empty($name))) {
            $allRules = $this->retrieveRules($field);
            if (!isset($allRules[$name])) {
                return;
            }
            unset($allRules[$name]);
        }
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
    public function forgetRules($field)
    {
        $this->forgetRule($field);
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
        $prefix = config('laravel-sms.storage.prefix', 'laravel_sms');
        $args = func_get_args();
        array_unshift($args, $this->token);
        $args = array_filter($args, function ($value) {
            return $value && is_string($value);
        });
        if (!(empty($args))) {
            $prefix .= $split . implode($split, $args);
        }

        return $prefix;
    }

    /**
     * 生成验证码短信通用内容
     *
     * @param string $code
     * @param int    $minutes
     *
     * @return string
     */
    protected function generateSmsContent($code, $minutes)
    {
        $content = config('laravel-sms.verifySmsContent');
        if (is_callable($content)) {
            $result = call_user_func_array($content, [$code, $minutes, $this->input()]);

            return is_string($result) ? $result : '';
        }

        return self::vsprintf($content, [$code, $minutes]);
    }

    /**
     * 生成模版数据
     *
     * @param string $code
     * @param int    $minutes
     * @param string $type
     *
     * @return array
     */
    protected function generateTemplateData($code, $minutes, $type)
    {
        $tplData = config('laravel-sms.templateData');
        if (empty($tplData)) {
            $tplData = [
                'code'    => $code,
                'minutes' => $minutes,
            ];
        }
        foreach ($tplData as $key => $value) {
            if (is_callable($value)) {
                $tplData[$key] = call_user_func_array($value, [$code, $minutes, $this->input(), $type]);
            }
        }

        return array_filter($tplData, function ($value) {
            return $value !== null;
        });
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
     * 获取手机号的字段名
     *
     * @throws LaravelSmsException
     *
     * @return string
     */
    protected static function getMobileField()
    {
        $config = config('laravel-sms.validation', []);
        foreach ($config as $key => $value) {
            if (!is_array($value)) {
                continue;
            }
            if (isset($value['isMobile']) && $value['isMobile']) {
                return $key;
            }
        }
        throw new LaravelSmsException("Don't find the name of mobile field, please define it in config file.");
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
            throw new LaravelSmsException("Generate storage failed, the class [$className] does not exists.");
        }
        $store = new $className();
        if (!($store instanceof Storage)) {
            throw new LaravelSmsException("Generate storage failed, the class [$className] does not implement the interface [Toplan\\Sms\\Storage].");
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
        $className = config('laravel-sms.storage.driver', null);
        if ($className && is_string($className)) {
            return $className;
        }
        $middleware = config('laravel-sms.routeAttributes.middleware', null);
        if ($middleware === 'web' || (is_array($middleware) && in_array('web', $middleware))) {
            return 'Toplan\Sms\SessionStorage';
        }
        if ($middleware === 'api' || (is_array($middleware) && in_array('api', $middleware))) {
            return 'Toplan\Sms\CacheStorage';
        }

        return 'Toplan\Sms\SessionStorage';
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
     * @return string|null
     */
    protected static function getStaticRule($field, $ruleName)
    {
        $data = self::getValidationConfigByField($field);

        return isset($data['staticRules'][$ruleName]) ? $data['staticRules'][$ruleName] : null;
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
        throw new LaravelSmsException("Don't find configuration information for [$field] field in config file, please define it.");
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
            throw new LaravelSmsException("The field name [$name] is illegal, because field name must be one of [$names].");
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
            if (isset($config[$name][$key])) {
                $templates[$name] = $config[$name][$key];
            }
        }

        return $templates;
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
        $length = (int) ($length ?: config('laravel-sms.verifyCode.length', 5));
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
        return (int) config('laravel-sms.verifyCode.validMinutes', 5);
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

    /**
     * 从配置文件获取可再次请求的最小时间间隔(秒)
     *
     * @return int
     */
    protected static function getInterval()
    {
        return (int) config('laravel-sms.interval', 60);
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
    public static function generateResult($pass, $type, $message = '', $data = [])
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
     * @param string        $template
     * @param array         $data
     * @param \Closure|null $onError
     *
     * @return string
     */
    public static function vsprintf($template, array $data, \Closure $onError = null)
    {
        if (!is_string($template)) {
            return '';
        }
        if ($template && !(empty($data))) {
            try {
                $template = vsprintf($template, $data);
            } catch (\Exception $e) {
                if ($onError) {
                    call_user_func($onError, $e);
                }
            }
        }

        return $template;
    }

    /**
     * 获取路径中的path部分
     *
     * @param string        $url
     * @param \Closure|null $onError
     *
     * @return string
     */
    public static function pathOfUrl($url, \Closure $onError = null)
    {
        $path = '';
        if (!is_string($url)) {
            return $path;
        }
        try {
            $parsed = parse_url($url);
            $path = $parsed['path'];
        } catch (\Exception $e) {
            if ($onError) {
                call_user_func($onError, $e);
            }
        }

        return $path;
    }
}
