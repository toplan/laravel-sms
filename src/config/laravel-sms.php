<?php

return array(

    /*
     * -----------------------------------
     * sms agent style
     * 指定代理发送平台
     * -----------------------------------
     *
     * 可选值有:'YunTongXun','YunPian'
     *
     */

    'agent' => 'YunTongXun',

    /*
     * -----------------------------------
     * 验证码短信相关配置
     * -----------------------------------
     */

    // 验证码短信通用内容
    'verifySmsContent' => "【your app name】亲爱的用户，您的验证码是%s。有效期为%s分钟，请尽快验证",

    // 验证码长度
    'codeLength' => 5,

    // 验证码有效时间长度，单位为分钟(minutes)
    'codeValidTime' => 5,

    /*
     *-----------------------------------
     * 指定Toplan\Sms\SmsController中的模型
     *-----------------------------------
     * 如果你继承并修改了Sms模型,需要在这里指定你的模型类
     */

    'smsModel' => 'Toplan\Sms\Sms',

    /*
     * -----------------------------------
     * 是否开启短信发送队列
     * -----------------------------------
     */

    'smsSendQueue' => true,

    /*
     * -----------------------------------
     * 指定队列任务,如果开启短信发送队列，则需要配置worker值
     * -----------------------------------
     */

    'smsWorker' => 'Toplan\Sms\SmsWorker',

    /*
     * -----------------------------------
     * 验证码短信发送规则
     * -----------------------------------
     *
     */

    'rules' => [
        //手机号检测规则
        'mobile' => [
            //发送前是否检测手机号合法性
            'is_check' => true,
            //默认规则
            'choose_rule' => 'check_mobile_unique',
            //可选规则
            'rules' => [
                //唯一性检测规则
                'check_mobile_unique' => '',//'unique:users,mobile',//适用于注册
                //存在性检测规则
                'check_mobile_exists' => 'exists:users',//适用于找回密码和系统内业务验证
                //add more mobile rules here
            ]
        ]
    ],

    /*
     * -----------------------------------
     * 备用代理器
     * -----------------------------------
     *
     * 使用默认或指定代理器发送失败后，系统可以启用其他代理器进行发送。
     *
     * enable:
     *       是否启用备用代理器
     *
     * agents:
     *       备用代理器组，排名分先后，越在前面的代理器会优先使用
     *       example : ['YunPian', ...]
     */

    'alternate' => [

        'enable' => false,

        'agents' => []

    ],

    /*
     * -----------------------------------
     * 云片代理器
     * -----------------------------------
     *
     * 官方网站：http://www.yunpian.com
     *
     */

    'YunPian' => [
        //验证码短信模板id
        //如果服务商不推荐使用模板短信，建议此处为空。内容会使用'verifySmsContent'
        //如果服务商只支持模板短信，此需要填写。
        'verifySmsTemplateId' => '',//not required,can be empty

        //是否重复发送队列任务中失败的短信(设置为false,可以拒绝再次发送失败的短信)
        'isResendFailedSmsInQueue' => false,

        //用户唯一标识，必须
        'apikey' => 'your api key',
    ],

    /*
     * -----------------------------------
     * 云通讯代理器
     * -----------------------------------
     *
     * 官方网站：http://www.yuntongxun.com/
     *
     */

    'YunTongXun' => [
        //验证码短信模板id
        //如果服务商不推荐使用模板短信，建议此处为空。内容会使用'verifySmsContent'
        //如果服务商只支持模板短信，此需要填写。
        'verifySmsTemplateId' => 'your template id',//required

        //是否重复发送队列任务中失败的短信(设置为false,可以拒绝再次发送失败的短信)
        'isResendFailedSmsInQueue' => false,

        //主帐号,对应开官网发者主账号下的 ACCOUNT SID
        'accountSid' => 'your account sid',

        //主帐号令牌,对应官网开发者主账号下的 AUTH TOKEN
        'accountToken' => 'your account token',

        //应用Id，在官网应用列表中点击应用，对应应用详情中的APP ID
        //在开发调试的时候，可以使用官网自动为您分配的测试Demo的APP ID
        'appId' => 'your app id',

        //请求地址
        //沙盒环境（用于应用开发调试）：sandboxapp.cloopen.com
        //生产环境（用户应用上线使用）：app.cloopen.com
        'serverIP' => 'app.cloopen.com',

        //请求端口，生产环境和沙盒环境一致
        'serverPort' => '8883',

        //REST版本号，在官网文档REST介绍中获得。
        'softVersion' => '2013-12-26',
    ],

    'sessionKey' => 'laravel_sms_data',

);