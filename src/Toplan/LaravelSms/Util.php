<?php

namespace Toplan\Sms;

use SuperClosure\Serializer;

class Util
{
    /**
     * 闭包函数序列化器
     *
     * @var Serializer
     */
    protected static $closureSerializer;

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

    /**
     * 获取闭包函数序列化器
     *
     * @return Serializer
     */
    public static function getClosureSerializer()
    {
        if (!self::$closureSerializer) {
            self::$closureSerializer = new Serializer();
        }

        return self::$closureSerializer;
    }

    /**
     * 序列化闭包函数
     *
     * @param \Closure $closure
     *
     * @return string
     */
    public static function serializeClosure(\Closure $closure)
    {
        return self::getClosureSerializer()->serialize($closure);
    }

    /**
     * 反序列化闭包函数
     *
     * @param $serializedClosure
     *
     * @return \Closure
     */
    public static function unserializeClosure($serializedClosure)
    {
        return self::getClosureSerializer()->unserialize($serializedClosure);
    }
}
