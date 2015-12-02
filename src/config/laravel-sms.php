<?php

return [

    /*
     * -----------------------------------
     * 验证码发送前合法性验证
     * -----------------------------------
     */
    'verify' => [
        'mobile' => [
            'enable' => true,

            //use rule
            'use' => 'check_mobile_unique',

            //available rules
            'rules' => [
                'check_mobile_unique' => 'unique:users,mobile',//适用于注册

                'check_mobile_exists' => 'exists:users',//适用于找回密码和系统内业务验证

                //add your rules here...
            ]
        ]
    ],

    /*
     * -----------------------------------
     * 验证码模块提示信息
     * -----------------------------------
     */
    'notifies' => [
        //required
        'request_invalid' => "请求无效，请在%s秒后重试",
    ],

    /*
     * -----------------------------------
     * 验证码短信相关配置
     * -----------------------------------
     * verifySmsContent: 验证码短信通用内容
     * codeLength: 验证码长度
     * codeValidTime: 验证码有效时间长度，单位为分钟(minutes)
     */
    'verifySmsContent' => "【your app signature】亲爱的用户，您的验证码是%s。有效期为%s分钟，请尽快验证",

    'codeLength' => 5,

    'codeValidTime' => 5,


    /*
     * -----------------------------------
     * Storage system
     * -----------------------------------
     * storePrefixKey: 存储key的prefix
     * storage: 存储方式
     */
    'storage' => 'Toplan\Sms\SessionStorage',

    'storePrefixKey' => 'laravel_sms_info',
];