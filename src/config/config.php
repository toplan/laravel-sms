<?php

return array(

    /**
     * 指定代理发送平台，可选值有:"YunTongXun"
     */
    'agent' => 'YunTongXun',

    /**
     * 以下三个为发送验证码短信相关配置
     */
    //模板/项目标示符
    'templateIdForVerifySms' => '',

    //验证码长度
    'codeLength' => 5,

    //验证码有效时间长度，单位为分钟
    'codeValidTime' => 5,//minutes

    /**
     * 指定Toplan\Sms\SmsController中的模型
     * 如果你继承并修改了Sms模型,需要在这里指定你的模型类
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
     * 短信发送规则
     */
    'rules' => [
        'mobile' => [
            //发送前是否检测手机号合法性
            'is_check' => true,
            //选择手机号检测规则
            'choose_rule' => 'check_mobile_unique',//default value is check_mobile_unique

            'rules' => [
                //唯一性检测规则
                'check_mobile_unique' => 'unique:users,mobile',//适用于注册
                //存在性检测规则
                'check_mobile_exists' => 'exists:users',//使用于找回密码和系统内业务验证
                //more rules..
            ]
        ]
    ],

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

    'sessionKey' => 'toplan_sms_sent_info_v1'
);