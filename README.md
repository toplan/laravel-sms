#Laravel Sms

###1. 关于v2
laravel-sms v2是基于[toplan/phpsms](https://github.com/toplan/phpsms)开发的适用于laravel框架的短信发送库。
相较于v1版本，v2是使用新思路重构的版本，并且升级备用代理器机制为[代理器均衡调度机制](#24-代理器均衡调度机制)。
phpsms为laravel-sms提供了全套的短信发送机制，而且phpsms也有自己的service provider，也就是说你完全可以在laravel框架下无障碍的独立使用phpsms。
这也是为什么使用laravel-sms会在项目中生成两个配置文件(phpsms.php和laravel-sms.php)的原因。

> config/phpsms.php负责配置代理器参数以及规划如何最优调度代理器(由phpsms提供)。
> config/laravel-sms.php则全职负责验证码发送/验证模块的配置(由laravel-sms提供)。

###2. why me

那么既然有了phpsms，为什么还需要laravel-sms呢？为了更进一步提高开发效率，laravel-sms利用phpsms提供的接口为laravel框架定制好了如下功能：

- 数据库记录日志
- 队列工作方式
- 验证码发送与验证模块

#特点

- 支持模板短信和内容短信 (由phpsms提供)
- [短信队列](#短信队列) (由phpsms提供)
- 支持语音验证码 (由phpsms提供)
- [代理器均衡调度机制](#24-代理器均衡调度机制) (由phpsms提供)
- 集成[国内主流第三方短信服务商](https://github.com/toplan/phpsms#服务商) (由phpsms提供)
- [自定义代理器](https://github.com/toplan/phpsms#自定义代理器)和性感的[寄生代理器](https://github.com/toplan/phpsms#寄生代理器) (由phpsms提供)
- 数据库记录/管理短信数据及其发送情况[可选]
- 集成[验证码短信发送/校验模块](#验证码模块)，从此告别重复写验证码短信发送与校验的历史
- 验证码发送/验证模块的[无session支持](#无会话支持)
- 灵活的[动态(自定义)数据验证](#动态验证规则)

#安装

在项目根目录下运行如下composer命令:
```php
//安装v2版本(推荐)
composer require 'toplan/laravel-sms:2.4.*',

//安装开发中版本
composer require 'toplan/laravel-sms:dev-master'
```

#公告

安装过旧版本(<2.4.0)的童鞋,在更新到2.4.0+版本时,建议先删除原有的`config/laravel-sms.php`文件,
然后再运行`php artisan vendor:publish`命令,使用前请务必阅读此文档,因为相比与2.4.0之前的版本有较大变化。

#快速上手v2

###1.注册服务提供器

在config/app.php文件中providers数组里加入：
```php
Toplan\PhpSms\PhpSmsServiceProvider::class,
Toplan\Sms\SmsManagerServiceProvider::class,
```

在config/app.php文件中的aliases数组里加入
```php
'PhpSms' => Toplan\PhpSms\Facades\Sms::class,
'SmsManager' => Toplan\Sms\Facades\SmsManager::class,
```

###2.参数配置

- 生成配置文件和migration文件

```php
php artisan vendor:publish
```

> 这里会生成两个配置文件，分别为phpsms.php和laravel-sms.php。
> 其中phpsms.php负责配置代理器参数以及规划如何调度代理器。
> laravel-sms.php则全职负责验证码发送/验证模块的配置。

- 配置代理器参数

在config/phpsms.php的`agents`数组中，找到你想要使用的代理器，并填写好配置信息。

- 代理器均衡调度

请在config/phpsms.php中设置代理器的均衡调度方案。
```php
'enable' => [
    //被使用概率为2/3
    'Luosimao' => '20',

    //被使用概率为1/3，且为备用代理器
    'YunPian' => '10 backup',

    //仅为备用代理器
    'YunTongXun' => '0 backup',
];
```

> **调度方案解析：**
> 如果按照以上配置，那么系统首次会尝试使用`Luosimao`或`YunPian`发送短信，且它们被使用的概率分别为`2/3`和`1/3`。
> 如果使用其中一个代理器发送失败，那么会启用备用代理器，按照配置可知备用代理器有`YunPian`和`YunTongXun`，那么会依次调用直到发送成功或无备用代理器可用。
> 值得注意的是，如果首次尝试的是`YunPian`，那么备用代理器将会只会使用`YunTongXun`，也就是会排除使用过的代理器。

###3.Enjoy it!

在控制器中发送触发短信，如下所示：
```php
use PhpSms;

// 接收人手机号
$to = '1828****349';
// 短信模版
$templates = [
    'YunTongXun' => 'your_temp_id',
    'SubMail'    => 'your_temp_id'
];
// 模版数据
$tempData = [
    'code' => '87392',
    'minutes' => '5'
];
// 短信内容
$content = '【签名】这是短信内容...';

// 只希望使用模板方式发送短信,可以不设置content(如:云通讯、Submail、Ucpaas)
PhpSms::make()->to($to)->template($templates)->data($tempData)->send();

// 只希望使用内容方式放送,可以不设置模板id和模板data(如:云片、luosimao)
PhpSms::make()->to($to)->content($content)->send();

// 同时确保能通过模板和内容方式发送,这样做的好处是,可以兼顾到各种类型服务商
PhpSms::make()->to($to)
     ->template($templates)
     ->data($tempData)
     ->content($content)
     ->send();

// 语言验证码
PhpSms::voice('89093')->to($to)->send();
```

#数据库日志

###1. 生成默认表

运行如下命令在数据库中生成`laravel_sms`表。

```php
php artisan migrate
```

###2. 开启权限

在配置文件`config/laravel-sms.php`中设置`dbLogs`为`true`。

```php
'dbLogs' => true,
```

#短信队列

###1. 启用/关闭队列

Laravel Sms已实现的短信队列默认是关闭的,判断当前队列状态：
```php
$enable = PhpSms::queue(); //true of false
```

开启/关闭队列的示例如下：
```php
//开启队列:
PhpSms::queue(true);

//关闭队列:
PhpSms::queue(false);
```

如果你开启了队列，需要运行如下命名监听队列
```php
php artisan queue:listen
```

###2. 队列自定义

如果你运行过`php artisan app:name`修改应用名称，或者需要自己实现队列工作逻辑，那么你需要进行自定义队列Job或者自定义队列流程（任选一种）。

- 方式1：自定义队列Job

该方式只需要你自己实现一个Job class，然后在`config/laravel-sms.php`中键为`queueJob`处配置你使用的Job class。
值得注意的是你的Job class构造函数的第一个参数是`Toplan\PhpSms\Sms`的实例，发送时你只需要调用他的`send()`方法即可。

- 方式2：自定义队列流程

在发送短信前，你可以完全重新定义你的队列流程！

```php
//example:
PhpSms::queue(function($sms, $data){
    //假设如此推入队列:
    $this->dispatch(new YourQueueJobClass($sms));
});
```

#验证码模块

可以直接访问example.com/sms/info查看该模块是否可用，并可在该页面里观察验证码短信发送数据，方便你进行调试。

###1.[服务器端]配置短信内容/模板

- 填写你的验证码短信内容或模板标示符

如果你使用了内容短信(如云片网络,Luosimao)，则使用或修改'verifySmsContent'的值。

> 配置文件为config/laravel-sms.php

```php
    'verifySmsContent' => '【填写签名】亲爱的用户，您的验证码是%s。有效期为%s分钟，请尽快验证'
```

如果你使用了模板短信(如云通讯,SubMail)/模版语音(如阿里大鱼)，需要到相应代理器中填写模板标示符。

> 配置文件为config/phpsms.php

```php
'YunTongXun' => [
    //短信模板标示符
    'verifySmsTemplateId' => 'your template id',
]
'Alidayu' => [
    //语音模板标示符
    'voiceVerifyTemplateId' => 'your tts code'
]
```

- 配置静态验证规则[可选]

> 配置文件为config/laravel-sms.php，你还可以配置[动态验证规则](#动态验证规则)

```php
'validation' => [
    'mobile' => [
        'enable'  => true,
        'default' => ...,
        'staticRules' => [
            ...
        ]
    ],
    ...
]
```

###2.[浏览器端]请求发送带验证码短信

该包已经封装好浏览器端的插件(兼容jquery/zepto)，只需要为发送按钮添加扩展方法即可实现发送短信。
```html
//js文件在laravel-sms包的js文件夹中，请复制到项目资源目录
<script src="/path/to/laravel-sms.js"></script>
<script>
$('#sendVerifySmsButton').sms({
    //laravel csrf token value
    //PS:该token仅为laravel框架的csrf验证，不是无会话json api所用的token
    token          : "{{csrf_token()}}",

    //access token for api
    //PS:如果你使用的是无会话json api，可以这样带上access token
    accessToken    : 'user token string...',

    //定义如何获取mobile的值
    mobileSelector : 'input[name="mobile"]',

    //定义手机号的检测规则,当然你还可以到配置文件中自定义你想要的任何规则
    mobileRule     : 'mobile_required',

    //是否请求语音验证码
    voice          : false,

    //定义服务器有消息返回时如何展示，默认为alert
    alertMsg       :  function (msg, type) {
        alert(msg);
    }
});
</script>
```

###3.[服务器端]合法性验证

用户填写验证码并提交表单到服务器时，在你的控制器中需要验证手机号和验证码是否正确，你只需要加上如下代码即可：
```php
use SmsManager;
...
//验证手机验证码
$validator = Validator::make($request->all(), [
    'mobile'     => 'required|confirm_mobile_not_change',
    'verifyCode' => 'required|verify_code|confirm_rule:mobile,mobile_required',
    //more...
]);
if ($validator->fails()) {
   //验证失败后建议清空存储的短信发送信息，防止用户重复试错
   SmsManager::forgetStatus();
   return redirect()->back()->withErrors($validator);
}
```

Note:
- `confirm_mobile_not_change` 验证用户手机号是否变更。
- `verify_code` 验证验证码是否合法。
- `confirm_rule:mobile,{$mobileRule}` 检测是否为非法请求，第一个值为手机号检测规则，必须和你在浏览器端js插件中填写的`mobileRule`的值一致。
- 请在语言包validation.php中做好翻译。

#动态验证规则

###1. 定义动态规则

```php
//方式1:
\SmsManager::storeRule('mobile', 'required|zh_mobile|unique:users,mobile,NULL,id,account_id,1');
//rule的name默认为当前uri

//方式2:
\SmsManager::storeRule('mobile', 'myRuleName', 'required|zh_mobile|unique:users,mobile,NULL,id,account_id,1');

//方式3
\SmsManager::storeRule('mobile', [
    'myRuleName' => 'required|zh_mobile|unique:users,mobile,NULL,id,account_id,1',
    'myRuleName2' => ...,
]);
```

> **小技巧:**存储的动态验证规则访问example.com/sms/info查看。

###2. 删除动态规则

```php
\SmsManager::forgetRule('mobile', 'myRuleName');
```

###3. 使用动态规则

- 客户端

设置`mobileRule`参数为要使用的动态验证规则的`name`值, 如果为空则默认为当前uri。

- 服务器端

```php
$rule = CUSTOM_RULE; //或者LARAVEL_SMS_CUSTOM_RULE
$validator = Validator::make($request->all(), [
    ...
    'verifyCode' => "required|verify_code:$token|confirm_rule:mobile,$rule",
    ...
]);
```

#无会话支持

###1. 请求地址

- 1.1 短信:
scheme://your-domain/sms/verify-code

- 1.2 语音:
scheme://your-domain/sms/voice-verify

###2. 基础参数

| 参数名  | 必填     | 说明        | 默认值       |
| ------ | :-----: | :---------: | :---------: |
| access_token | 是   | 植入header或参数中均可|   |
| mobile | 是      | 手机号码      |             |
| mobileRule | 否  | 手机号检测规则 | `''`        |
| seconds | 否     | 请求间隔(秒)  | `60`        |

###3. 服务端准备

在`config/laravel-sms.php`中配置路由器组中间件`middleware`。

```php
'middleware' => 'api',
```

#License

MIT