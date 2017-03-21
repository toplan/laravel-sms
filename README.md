# Laravel Sms

一个基于`Laravel`框架的功能强大的手机号合法性验证解决方案。

### 1. 关于v2
`laravel-sms` v2是基于[toplan/phpsms](https://github.com/toplan/phpsms)开发的适用于`Laravel`框架的短信发送库(当然`laravel-sms`的功能还不仅于此)。
相较于v1版本，v2是使用新思路重构的版本，并且升级备用代理器机制为代理器均衡调度机制。
`phpsms`为`laravel-sms`提供了全套的短信发送机制，而且`phpsms`也有自己的 service provider ，也就是说你完全可以在`Laravel`框架下无障碍的独立使用`phpsms`。
这也是为什么使用`laravel-sms`会在项目中生成两个配置文件(phpsms.php和laravel-sms.php)的原因。

> config/phpsms.php负责配置代理器参数以及规划如何最优调度代理器(由phpsms提供)。
> config/laravel-sms.php则全职负责验证码发送/验证模块的配置(由laravel-sms提供)。

### 2. why me

为了更进一步提高开发效率，`laravel-sms`为`Laravel`框架定制好了如下功能：

- 可扩展的[发送前数据验证](#发送前数据验证)
- 集成[验证码发送与验证模块](#验证码模块)，从此告别重复写验证码短信发送与校验的历史
- 灵活的[动态验证规则](#4-动态验证规则)
- 可选的[数据库日志](#数据库日志)
- 集成[短信队列](#短信队列)
- 验证码发送与验证模块的[无session支持](#无会话支持)

### 3. 如何快速开始?

上面提了这么多特性，那么如何快速上手并体验一下验证码发送与验证呢?只需要依次完成以下三个步骤即可。

- step1: [安装](#安装)
- step2: [准备工作](#准备工作)
- step3: [验证码模块](#验证码模块)

# 公告

- QQ群:159379848
- [捐赠](#donate)
- 旧版本更新到2.5.0+版本时，请先删除原有的`config/laravel-sms.php`文件和`laravel-sms.js`文件(如果有用到)


# 安装

在项目根目录下运行如下composer命令:
```php
//安装v2版本(推荐)
composer require toplan/laravel-sms:2.5.*

//安装开发中版本
composer require toplan/laravel-sms:dev-master
```

# 准备工作

### 1.注册服务提供器

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

# 发送前数据验证

### 1. 声明

当客户端向服务器端请求发送验证码短信/语音时，服务器端需要对接收到的数据(本库将其称为`field`)进行验证，只有在所有需验证的数据都通过了验证才会向第三方服务提供商发起请求。对于每项你想验证的`field`，不管是使用静态验证规则还是[动态验证规则](#4-动态验证规则)，都需要提前到配置文件(`config/laravel-sms.php`)中声明，并做好必要的配置。

> 本文档中所说的`服务器端`是我们自己的应用系统，而非第三方短信服务提供商。

#### 1.1 配置项
对于每项数据,都有以下三项可设置:

| 配置项       | 必填  | 说明        |
| ----------- | :---: | :---------: |
| isMobile    | 否    | 该字段是否为手机号码    |
| enable      | 是    | 发送前是否需要对该字段进行验证 |
| default     | 否    | 该字段的默认静态验证规则 |
| staticRules | 否    | 该字段的所有静态验证规则 |

#### 1.2 示例

```php
'validation' => [
    //内置的mobile参数的验证配置
    'mobile' => [
        //是否为手机号字段:
        'isMobile'    => true,
        //是否开启该字段的检测:
        'enable'      => true,
        //默认的静态验证规则:
        'default'     => 'mobile_required',
        //静态验证规则:
        'staticRules' => [
            //name => rule
            'mobile_required' => 'required|zh_mobile',
            ...
        ],
    ],

    //自定义你可能需要验证的字段
    'image_captcha' => [
        'enable' => true,
    ],
],
```

### 2. 使用

静态验证规则和动态验证规则的使用方法一致。

#### 2.1 客户端

通过`{field}_rule`参数告知服务器`{field}`参数需要使用的验证规则的名称。

> 如:`mobile_rule`参数可以告知服务器在验证`mobile`参数时使用什么验证规则，
> `image_captcha_rule`参数可以告知服务器在验证`image_captcha`参数时使用什么验证规则。

#### 2.2 服务器端

[示例见此](#3-服务器端合法性验证)

# 验证码模块

可以直接访问`your-domain/laravel-sms/info`查看该模块是否可用，并可在该页面里观察验证码短信发送状态，方便你进行调试。

> 如果是api应用(无session)需要带上access token: your-domain/laravel-sms/info?access_token=xxxx

### 1. [服务器端]配置短信内容/模板

- 配置通用内容

如果你使用了内容短信(如云片网络,Luosimao)，则使用或修改'verifySmsContent'的值。
> 配置文件为config/laravel-sms.php
```php
'verifySmsContent' => '【填写签名】亲爱的用户，您的验证码是%s。有效期为%s分钟，请尽快验证',
```

- 配置模版数据

如果你使用了模板短信，你可以配置你准备使用哪些模版数据。
> 配置文件为config/laravel-sms.php
```php
'templateData' => [
    'code' => function ($code) {
        return $code;
    },
    ...
],
```

- 配置模版id

如果你使用了模板短信，需要到相应代理器中填写模板标示符。
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

### 2. [浏览器端]请求发送验证码短信

该包已经封装好浏览器端的插件(兼容jquery/zepto)，只需要为发送按钮添加扩展方法即可实现发送短信。

> js文件在本库的js文件夹中，请复制到项目资源目录

```html
<script src="/path/to/laravel-sms.js"></script>
<script>
$('#sendVerifySmsButton').sms({
    //laravel csrf token
    token       : "{{csrf_token()}}",
    //请求间隔时间
    interval    : 60,
    //请求参数
    requestData : {
        //手机号
        mobile : function () {
            return $('input[name=mobile]').val();
        },
        //手机号的检测规则
        mobile_rule : 'mobile_required'
    }
});
</script>
```

> laravel-sms.js的更多用法请[见此](#laravel-smsjs)

### 3. [服务器端]合法性验证

用户填写验证码并提交表单到服务器时，在你的控制器中需要验证手机号和验证码是否正确，你只需要加上如下代码即可：
```php
use SmsManager;
...

//验证数据
$validator = Validator::make($request->all(), [
    'mobile'     => 'required|confirm_mobile_not_change|confirm_rule:mobile_required',
    'verifyCode' => 'required|verify_code',
    //more...
]);
if ($validator->fails()) {
   //验证失败后建议清空存储的发送状态，防止用户重复试错
   SmsManager::forgetState();
   return redirect()->back()->withErrors($validator);
}
```
> `confirm_mobile_not_change`, `verify_code`, `confirm_rule`的详解请参看[Validator扩展](#validator扩展)

# Validator扩展

#### zh_mobile
检测标准的中国大陆手机号码。

#### confirm_mobile_not_change
检测用户提交的手机号是否变更。

#### verify_code
检测验证码是否合法且有效，如果验证码错误，过期或超出尝试次数都无法验证通过。

#### confirm_rule:$ruleName
检测验证规则是否合法，后面跟的第一个参数为待检测的验证规则的名称。
如果不填写参数`$ruleName`（不写冒号才表示不填写哦），系统会尝试设置其为前一个访问路径的path部分。

# 数据库日志

### 1. 生成数据表

运行如下命令在数据库中生成`laravel_sms`表。

```php
php artisan migrate
```

### 2. 开启权限

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

### 2. 队列自定义

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

# 无会话支持

### 1. 服务器端准备

在`config/laravel-sms.php`中配置路由器组中间件`middleware`。

```php
//example:
'middleware' => ['api'],
```

### 2. Access Token

Access Token值建议设置在请求头中的`Access-Token`上,当然也可以带在请求参数`access_token`中。

### 3. 请求地址

- 短信:
scheme://your-domain/laravel-sms/verify-code

- 语音:
scheme://your-domain/laravel-sms/voice-verify

### 4. 默认参数

| 参数名  | 必填     | 说明         |
| ------ | :-----: | :----------: |
| mobile | 是      | 手机号码      |
| mobile_rule | 否 | 手机号检测规则 |

### 5. 响应数据

| 参数名  | 说明              |
| ------ | :--------------: |
| success| 是否请求发送成功    |
| type   | 类型              |
| message| 详细信息           |

# API

`laravel-sms`提供的所有功能都是由该章节的接口和`phpsms`的接口实现的。虽然通过配置文件你可以完成基本所有的常规需求，但是对于更加变态（个性化）的需求，你就可能需要在`laravel-sms`的基础上做定制化的开发，在这种情况下，阅读该章节或许能给你提供必要的帮助，否则你可以忽略该章节哦。

```php
use SmsManager;
```

### 1. 发送前校验

#### validateSendable()

校验是否可进行发送。如果校验未通过，返回数据中会包含错误信息。
```php
$result = SmsManager::validateSendable();
```

#### validateFields([$input][, $validation])

校验数据合法性。如果校验未通过，返回数据中会包含错误信息。
```php
//使用内置的验证逻辑
$result = SmsManager::validateFields();

//自定义验证逻辑
$result = SmsManager::validateFields(function ($fields, $rules) {
    //在这里做你的验证处理，并返回结果...
    //如：
    return Validator::make($fields, $rules);
});
```

### 2. 发送

#### requestVerifySms()

请求发送验证码短信。
```php
$result = Manager::requestVerifySms();
```

#### requestVoiceVerify()

请求发送语音验证码。
```php
$result = Manager::requestVoiceVerify();
```

### 3. 发送状态

#### state([$key][, $default])

获取当前的发送状态（非持久化的）。
```php
//example:
$state = SmsManager::state();
```

#### retrieveState([$key])

获取持久化存储的发送状态，即存储到`session`或缓存中的状态数据。
```php
$state = SmsManager::retrieveState();
```

#### updateState($name, $value)

更新持久化存储的发送状态。
```php
SmsManager::updateState('key', 'value');
SmsManager::updateState([
    'key' => 'value'
]);
```

#### forgetState()

删除持久化存储的发送状态。
```php
SmsManager::forgetState();
```

### 4. 动态验证规则

#### storeRule($field[, $name], $rule);

定义客户端数据（字段）的动态验证规则。
```php
//方式1:
//如果不设置name,那么name默认为当前访问路径的path部分
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

#### retrieveRule($field[, $name])

获取字段的指定名称的动态验证规则。
```php
$rule = SmsManager::retrieveRule('mobile', 'myRuleName');
```

#### retrieveRules($field)

获取字段的所有动态验证规则。
```php
$rules = SmsManager::retrieveRules('mobile');
```

#### forgetRule($field[, $name])

删除字段的指定名称的动态验证规则。
```php
SmsManager::forgetRule('mobile', 'myRuleName');
```

#### forgetRules($field)

删除字段的所有动态验证规则。
```php
SmsManager::forgetRules('mobile');
```

### 5. 客户端数据

#### input([$key][, $default])

获取客户端传递来的数据。客户端数据会自动注入到配置文件(`laravel-sms.php`)中闭包函数的第三个参数中。
```php
//example:
$mobileRuleName = SmsManager::input('mobile_rule');
$all = SmsManager::input();
```

# 附录

### PhpSms API

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

### laravel-sms.js

```javascript
$('#sendVerifySmsButton').sms({
    //laravel csrf token
    //该token仅为laravel框架的csrf验证,不是access_token!
    token       : "{{csrf_token()}}",

    //请求间隔时间
    interval    : 60,

    //是否请求语音验证码
    voice       : false,

    //请求参数
    requestData : {
        //手机号
        mobile : function () {
            return $('input[name=mobile]').val();
        },
        //手机号的检测规则
        mobile_rule : 'mobile_required'
    }

    //定义服务器有消息返回时如何展示，默认为alert
    alertMsg    : function (msg, type) {
        alert(msg);
    }
});
```

# License

MIT

# Donate

码路漫漫，赠一杯咖啡给作者？

![支付宝](donate-alipay.jpg)
