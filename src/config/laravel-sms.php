<?php

return array(

    /*
     * -----------------------------------
     * sms agent style
     * 指定代理器(服务商)
     * -----------------------------------
     * 可选值有:'YunTongXun', 'YunPian', 'SubMail', 'Luosimao'
     */

    'agent' => 'YunPian',

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
     * true为开启,false为关闭
     */

    'smsSendQueue' => true,

    /*
     * -----------------------------------
     * 指定队列任务
     * -----------------------------------
     * 如果开启短信发送队列，则需要配置worker值
     */

    'smsWorker' => 'Toplan\Sms\SmsWorker',

    /*
     * -----------------------------------
     * 备用代理器
     * -----------------------------------
     * 使用默认或指定代理器发送失败后，系统可以启用其他代理器进行发送。
     * enable:
     *       是否启用备用代理器，true为开启，false为关闭
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
     * 验证码短信发送前合法性验证
     * -----------------------------------
     */

    'verify' => [
        //手机号检测规则
        'mobile' => [
            //是否可用
            'enable' => true,
            //默认规则
            'choose_rule' => 'check_mobile_unique',
            //可选规则
            'rules' => [
                //唯一性检测规则
                'check_mobile_unique' => 'unique:users,mobile',//适用于注册

                //存在性检测规则
                'check_mobile_exists' => 'exists:users',//适用于找回密码和系统内业务验证

                //add your rules here...
            ]
        ]
    ],

    /*
     * -----------------------------------
     * 验证码短信相关配置
     * -----------------------------------
     */

    // 验证码短信通用内容, 提供给内容短信(如YuPian,Luosimao)的验证码短信内容
    'verifySmsContent' => "【your app signature】亲爱的用户，您的验证码是%s。有效期为%s分钟，请尽快验证",

    // 验证码长度
    'codeLength' => 5,

    // 验证码有效时间长度，单位为分钟(minutes)
    'codeValidTime' => 5,


    /*
     * -----------------------------------
     * 云片代理器
     * -----------------------------------
     * 官方网站：http://www.yunpian.com
     * 只支持内容短信
     */

    'YunPian' => [

        //是否重复发送队列任务中失败的短信(设置为false,可以拒绝再次发送失败的短信)
        'isResendFailedSmsInQueue' => false,

        //用户唯一标识，必须
        'apikey' => 'your api key',
    ],

    /*
     * -----------------------------------
     * 云通讯代理器
     * -----------------------------------
     * 官方网站：http://www.yuntongxun.com/
     * 只支持模板短信
     */

    'YunTongXun' => [

        //验证码短信模板id
        'verifySmsTemplateId' => 'your verify sms template id',

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

    /*
     * -----------------------------------
     * SubMail代理器
     * -----------------------------------
     * 官方网站：http://submail.cn/
     * 只支持模板短信
     */
    'SubMail' => [

        'verifySmsTemplateId' => 'your verify sms template id',

        'isResendFailedSmsInQueue' => false,

        'appid' => 'your app id',

        'signature' => 'your app key',
    ],

    /*
     * -----------------------------------
     * luosimao
     * -----------------------------------
     * 官方网站：http://luosimao.com
     * 只支持内容短信
     */
    'Luosimao' => [

        'isResendFailedSmsInQueue' => false,

        // API key是验证密码
        // 在管理中心->短信服务->触发发送下查看
        'apikey' => 'your api key',
    ],

    /*
     * -----------------------------------
     * session key
     * -----------------------------------
     * store verify sms data in session
     */
    'sessionKey' => 'laravel_sms_data',

);