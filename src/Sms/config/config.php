<?php

return array(

    /**
     * 指定代理发送平台，可选值有:"YunTongXun"
     */
    'agent' => 'YunTongXun',


    /**
     * 指定Toplan\Sms\SmsController中的模型
     */
    'smsModel' => 'Toplan\Sms\Sms',


    /**
     * 是否开启短信发送队列
     */
    'smsSendQueue' => true,


    /**
     * 指定队列任务,如果开启短信发送队列，则需要配置worker值
     */
    'smsWorker' => 'Toplan\Sms\SmsWorker',


    /**
     * 云通讯代理器配置
     * 官方网站：http://www.yuntongxun.com/
     */
    'YunTongXun' => [
        //是否重复发送队列任务中失败的短信(设置为false,可以拒绝再次发送失败的短信)
        'isResendFailedSmsInQueue' => false,

        //主帐号,对应开官网发者主账号下的 ACCOUNT SID
        'accountSid'    => '',

        //主帐号令牌,对应官网开发者主账号下的 AUTH TOKEN
        'accountToken'  => '',

        //应用Id，在官网应用列表中点击应用，对应应用详情中的APP ID
        //在开发调试的时候，可以使用官网自动为您分配的测试Demo的APP ID
        'appId'         => '',

        //请求地址
        //沙盒环境（用于应用开发调试）：sandboxapp.cloopen.com
        //生产环境（用户应用上线使用）：app.cloopen.com
        'serverIP'      => 'app.cloopen.com',

        //请求端口，生产环境和沙盒环境一致
        'serverPort'    => '8883',

        //REST版本号，在官网文档REST介绍中获得。
        'softVersion'   => '2013-12-26',
    ],

);