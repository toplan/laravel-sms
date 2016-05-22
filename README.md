#Laravel Sms

一个基于`Laravel`框架的验证码短信/语音发送，校验和发送结果管理的解决方案。

###1. 关于v2
laravel-sms v2是基于[toplan/phpsms](https://github.com/toplan/phpsms)开发的适用于laravel框架的短信发送库。
相较于v1版本，v2是使用新思路重构的版本，并且升级备用代理器机制为[代理器均衡调度机制](#24-代理器均衡调度机制)。
phpsms为laravel-sms提供了全套的短信发送机制，而且phpsms也有自己的service provider，也就是说你完全可以在laravel框架下无障碍的独立使用phpsms。
这也是为什么使用laravel-sms会在项目中生成两个配置文件(phpsms.php和laravel-sms.php)的原因。

> config/phpsms.php负责配置代理器参数以及规划如何最优调度代理器(由phpsms提供)。
> config/laravel-sms.php则全职负责验证码发送/验证模块的配置(由laravel-sms提供)。

###2. why me

那么既然有了phpsms，为什么还需要laravel-sms呢？为了更进一步提高开发效率，laravel-sms利用phpsms提供的接口为laravel框架定制好了如下功能：

- 可扩展的[发送前数据验证](#发送前数据验证)
- 集成[验证码发送与验证模块](#验证码模块)，从此告别重复写验证码短信发送与校验的历史
- 灵活的[动态验证规则](#2-动态验证规则)
- 可选的[数据库日志](#数据库日志)
- 集成[短信队列](#短信队列)
- 验证码发送与验证模块的[无session支持](#无会话支持)

###3. 由PhpSms提供的特性

- 支持模板短信和内容短信
- 支持语音验证码
- 松散耦合的队列接口
- 代理器均衡调度机制
- 集成[国内主流第三方短信服务商](https://github.com/toplan/phpsms#服务商)
- [自定义代理器](https://github.com/toplan/phpsms#自定义代理器)和[寄生代理器](https://github.com/toplan/phpsms#寄生代理器)

###4. 如何快速开始?

上面提了这么多特性，那么如何快速上手并体验一下验证码发送与验证呢?只需要依次完成以下三个步骤即可。

- step1: [安装](#安装)
- step2: [准备工作](#准备工作)
- step3: [验证码模块](#验证码模块)

#公告!!!

安装过旧版本(<2.4.0)的童鞋,在更新到2.4.0+版本时,务必先删除原有的`config/laravel-sms.php`文件和`laravel-sms.js`文件(如果有用到),
然后再运行`php artisan vendor:publish`命令,而且在使用新版本前请再阅读下此文档,因为2.4.0版本有较大变化。

#安装

在项目根目录下运行如下composer命令:
```php
//安装v2版本(推荐)
composer require 'toplan/laravel-sms:2.4.*',

//安装开发中版本
composer require 'toplan/laravel-sms:dev-master'
```

#准备工作

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

- 配置代理器参数

在config/phpsms.php的`agents`数组中，找到你想要使用的代理器，并填写好配置信息。

- 代理器均衡调度

在config/phpsms.php中设置代理器的均衡调度方案。
```php
'scheme' => [
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

#发送前数据验证

###1. 声明

当客服端向服务器端请求发送验证码短信/语音时，服务器端需要对接收到的数据(本库将其称为`field`)进行验证，只有在所有需验证的数据都验证通过了才会向第三方服务提供商请求发送验证码短信/语音。
对于每项你想验证的数据(`field`)，不管是使用静态验证规则还是[动态验证规则](#2-动态验证规则)，都需要提前到配置文件(`config/laravel-sms.php`)中声明，并做好必要的配置。

> 本文档中所说的`服务器端`是我们自己的应用系统，而非第三方短信服务提供商。

####配置项
对于每项数据,都有以下三项设置:

- enable

服务器端在向第三方服务提供商请求发送验证码短信/语音前是否需要对该数据进行验证。(必要)

- default

该数据的默认静态验证规则名。(可选)

- staticRules

该数据的所有静态验证规则。(可选)

####示例

```php
'validation' => [
    // 内置的mobile字段的验证设置:
    'mobile' => [
        //是否开启该字段的检测:
        'enable'      => true,
        //默认的静态验证规则:
        'default'     => 'mobile_required',
        //静态验证规则:
        'staticRules' => [
            // name => rule
            'mobile_required'     => 'required|zh_mobile',
            ...
        ]
    ]
    // 配置你可能需要验证的字段
    'yourField' => [
        'enable' => true,
        ...
    ]
]
```

###2. 使用

> 静态验证规则和动态验证规则的使用方法一致。

####客户端

通过`{field}_rule`参数告知服务器`{field}`参数需要使用的验证规则的名称。
如`mobile_rule`参数可以告知服务器在验证`mobile`参数使用什么验证规则。

####服务器端

[示例见此](#3服务器端合法性验证)

#验证码模块

可以直接访问`your-domain/laravel-sms/info`查看该模块是否可用，并可在该页面里观察验证码短信发送数据，方便你进行调试。

> 如果是api应用(无session)需要带上access token: your-domain/laravel-sms/info?access_token=xxxx

###1. [服务器端]配置短信内容/模板

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

###2. [浏览器端]请求发送验证码短信

该包已经封装好浏览器端的插件(兼容jquery/zepto)，只需要为发送按钮添加扩展方法即可实现发送短信。

> js文件在本库的js文件夹中，请复制到项目资源目录

```html
<script src="/path/to/laravel-sms.js"></script>
<script>
$('#sendVerifySmsButton').sms({
    //laravel csrf token
    token           : "{{csrf_token()}}",
    //定义如何获取mobile的值
    mobile_selector : 'input[name=mobile]',
    //手机号的检测规则
    mobile_rule     : 'mobile_required',
    //请求间隔时间
    interval        : 60
});
</script>
```

> laravel-sms.js的更多用法请[见此](#laravel-smsjs)

###3. [服务器端]合法性验证

用户填写验证码并提交表单到服务器时，在你的控制器中需要验证手机号和验证码是否正确，你只需要加上如下代码即可：
```php
use SmsManager;
...

//验证数据
$validator = Validator::make($request->all(), [
    'mobile'     => 'required|confirm_mobile_not_change',
    'verifyCode' => 'required|verify_code|confirm_rule:mobile,mobile_required',
    //more...
]);
if ($validator->fails()) {
   //验证失败后建议清空存储的发送状态，防止用户重复试错
   SmsManager::forgetState();
   return redirect()->back()->withErrors($validator);
}
```
> `confirm_mobile_not_change`, `verify_code`, `confirm_rule`的详解请参看[Validator扩展](#validator扩展)

#API

```php
use SmsManager;
```

###1. 发送状态

#####retrieveState()

获取发送状态。

```php
SmsManager::retrieveState()
```

#####forgetState()

删除发送状态。

```php
SmsManager::forgetState()
```

###2. 动态验证规则

#####storeRule($field[, $name], $rule)

定义数据的动态验证规则。

```php
//方式1:
//如果不设置name,那么name默认为当前访问路径的uri
SmsManager::storeRule('mobile', 'required|zh_mobile|unique:users,mobile,NULL,id,account_id,1');

//方式2:
SmsManager::storeRule('mobile', 'myRuleName', 'required|zh_mobile|unique:users,mobile,NULL,id,account_id,1');

//方式3
SmsManager::storeRule('mobile', [
    'myRuleName' => 'required|zh_mobile|unique:users,mobile,NULL,id,account_id,1',
    'myRuleName2' => ...,
]);
```

> 存储的动态验证规则可通过访问`your-domain/laravel-sms/info`查看。动态验证规则的名称最好不要和静态验证规则同名,因为静态验证规则的优先级更高。

#####retrieveRules($field)
获取某项数据的所有动态验证规则。
```php
SmsManager::retrieveRules('mobile');
```

#####retrieveRule($field, $name)
获取某项数据的指定名称的动态验证规则。
```php
SmsManager::retrieveRule('mobile', 'myRuleName');
```

#####forgetRule($field, $name)
删除某项数据的指定名称的动态验证规则。
```php
SmsManager::forgetRule('mobile', 'myRuleName');
```

#Validator扩展

####zh_mobile
检测标准的中国大陆手机号码。

####confirm_mobile_not_change
检测用户提交的手机号是否变更。

####verify_code
检测验证码是否合法。

####confirm_rule:$field,$ruleName
检测验证规则是否合法，第一个值为字段名称，第二个值为使用的验证规则的名称。
如果第二项参数(`$ruleName`)不填写,系统会尝试设置其为前一个访问路径的uri。

#数据库日志

###1. 生成数据表

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

#无会话支持

###1. 服务器端准备

在`config/laravel-sms.php`中配置路由器组中间件`middleware`。

```php
//example:
'middleware' => ['api'],
```

###2. Access Token

Access Token值建议设置在请求头中的`Access-Token`上,当然也可以带在请求参数`access_token`中。

###3. 请求地址

- 短信:
scheme://your-domain/laravel-sms/verify-code

- 语音:
scheme://your-domain/laravel-sms/voice-verify

###4. 基础参数

| 参数名  | 必填     | 说明        | 默认值       |
| ------ | :-----: | :---------: | :---------: |
| mobile | 是      | 手机号码      |             |
| mobile_rule | 否 | 手机号检测规则 | `''`        |
| interval | 否    | 请求间隔时间(秒)  | `60`        |

###5. 响应数据

| 参数名  | 说明              |
| ------ | :--------------: |
| success| 是否请求发送成功    |
| type   | 类型              |
| message| 详细信息           |

#附录

###PhpSms API

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

###laravel-sms.js

```javascript
$('#sendVerifySmsButton').sms({
    //laravel csrf token
    //该token仅为laravel框架的csrf验证,不是access_token!
    token           : "{{csrf_token()}}",

    //access token for api
    access_token    : '...',

    //定义如何获取mobile的值
    mobile_selector : 'input[name=mobile]',

    //手机号的检测规则
    mobile_rule     : 'mobile_required',

    //请求间隔时间
    interval        : 60,

    //是否请求语音验证码
    voice           : false,

    //定义服务器有消息返回时如何展示，默认为alert
    alertMsg       :  function (msg, type) {
        alert(msg);
    }
});
```

#License

MIT