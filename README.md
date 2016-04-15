#Laravel Sms

###1. 关于v2
laravel-sms v2是基于[phpsms](https://github.com/toplan/phpsms)针对laravel框架二次封装的短信发送库。
相较于v1版本，v2是使用新思路全新重构的版本，并且升级备用代理器机制为[代理器均衡调度机制](#24-代理器均衡调度机制)。
> phpsms的请求负载均衡功能由[task-balancer](https://github.com/toplan/task-balancer)提供。

phpsms为laravel-sms提供了全套的短信发送机制，而且phpsms也有自己的service provider，也就是说你完全可以在laravel框架下无障碍的独立使用phpsms。
这也是为什么使用laravel-sms会在项目中生成两个配置文件(phpsms.php和laravel-sms.php)的根本原因。

> config/phpsms.php负责配置代理器参数以及规划如何最优调度代理器(由phpsms提供)。
> config/laravel-sms.php则全职负责验证码发送/验证模块的配置(由laravel-sms提供)。

###2. why me

那么既然有了phpsms，为什么还需要laravel-sms呢？
为了更进一步提高开发效率，laravel-sms在phpsms的基础上针对laravel框架定制好了如下功能：

* 队列工作方式
* 数据库记录日志
* 验证码发送/验证模块

# 特点

1. 支持模板短信和内容短信(由phpsms提供)。
2. [短信队列](#短信队列)(由phpsms提供)。
3. 支持语音验证码(由phpsms提供)。
4. [代理器均衡调度机制](#24-代理器均衡调度机制)(由phpsms提供)。
5. 集成[国内主流第三方短信服务商](https://github.com/toplan/phpsms#服务商)(由phpsms提供)
6. [自定义代理器](https://github.com/toplan/phpsms#自定义代理器)和性感的[寄生代理器](https://github.com/toplan/phpsms#寄生代理器)。(由phpsms提供)。
7. 数据库记录/管理短信数据及其发送情况[可选]。
8. 集成[验证码短信发送/校验模块](#验证码短信模块)，从此告别重复写验证码短信发送与校验的历史。
9. 验证码发送/验证模块的[无session支持](#无会话支持)。

# 安装
在项目根目录下运行如下composer命令:
```php
   //安装2.3版本(推荐)
   composer require 'toplan/laravel-sms:~2.3.1',

   //安装开发中版本
   composer require 'toplan/laravel-sms:dev-master'
```

> **安装1.0**
>
> [v1.0文档](https://github.com/toplan/laravel-sms/tree/l5)
> ```php
>   composer require 'toplan/laravel-sms:1.0.2',
> ```

# 快速上手v2

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

#####2.1 请先运行如下命令生成配置文件和migration文件

```php
   php artisan vendor:publish
```
> 说明：
> 这里会生成两个配置文件，分别为phpsms.php和laravel-sms.php。
> 其中phpsms.php负责配置代理器参数以及规划如何调度代理器。
> laravel-sms.php则全职负责验证码发送/验证模块的配置。

#####2.2 在数据库中生成`laravel_sms`表[可选]

```php
   php artisan migrate
```

#####2.3 配置代理服务商的相关参数

在config/phpsms.php的`agents`数组中，找到你想要使用的代理器，并填写好配置信息。

#####2.4 代理器均衡调度机制

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

**调度方案解析：**
如果按照以上配置，那么系统首次会尝试使用`Luosimao`或`YunPian`发送短信，且它们被使用的概率分别为`2/3`和`1/3`。
如果使用其中一个代理器发送失败，那么会启用备用代理器，按照配置可知备用代理器有`YunPian`和`YunTongXun`，那么会依次调用直到发送成功或无备用代理器可用。
值得注意的是，如果首次尝试的是`YunPian`，那么备用代理器将会只会使用`YunTongXun`，也就是会排除使用过的代理器。

###3.Enjoy it!

在控制器中发送触发短信，如：
```php
  //只希望使用模板方式发送短信,可以不设置内容content (如云通讯,Submail)
  PhpSms::make()->to('1828****349')->template('Luosimao', 'xxx')->data(['12345', 5])->send();

  //只希望使用内容方式发送,可以不设置模板id和模板数据data (如云片,luosimao)
  PhpSms::make()->to('1828****349')->content('【Laravel SMS】亲爱的张三，欢迎访问，祝你工作愉快。')->send();

  //同时确保能通过模板和内容方式发送。这样做的好处是，可以兼顾到各种代理器(服务商)！
  PhpSms::make()
          ->to('1828****349')
          ->template([
              'YunTongXun' => '123',
              'SubMail'    => '123'
          ])
          ->data(['张三'])
          ->content('【签名】亲爱的张三，欢迎访问，祝你工作愉快。')
          ->send();

  //语音验证码
  PhpSms::voice($code)->to($to)->send();
```

###4.语法糖

详情请参看[phpsms](https://github.com/toplan/phpsms#api)

#短信队列

###1. 启用/关闭队列

`laravel-sms`已实现的短信队列默认是关闭的,判断当前队列状态：
```php
$enable = PhpSms::queue();//return true of false
```

开启/关闭队列的示例如下：
```php
//开启队列
PhpSms::queue(true);
//关闭队列
PhpSms::queue(false);
```

如果你开启了队列，需要运行如下命名监听队列
```php
php artisan queue:listen
```

###2. 队列自定义

如果你运行过`php artisan app:name`修改应用名称，或者需要自己实现队列工作逻辑，那么你需要进行自定义队列Job或者自定义队列流程（任选一种）。

* 方式1：自定义队列Job

该方式需要你自己实现一个Job class，然后在config/laravel-sms.php中键为`queueJob`处配置你使用的Job class哦。
值得注意的是你的Job class构造函数的第一个参数是`Toplan\PhpSms\Sms`的实例，你只需要调用他的`send()`方法即可。

* 方式2：自定义队列流程

在发送短信前，你可以完全重新定义你的队列流程哦！

```php
//example:
PhpSms::queue(function($sms, $data){
    ...
    //假设如此推入队列:
    $this->dispatch(new YourQueueJobClass($sms));
    ...
    //请务必返回如下数据用以标记推入队列成功(否则会报错)：
    return [
        'success' => true,
        'after_push_to_queue' => true,
    ];
});
```

#验证码短信模块

可以直接访问example.com/sms/info查看该模块是否可用，并可在该页面里观察验证码短信发送数据，方便你进行调试。

###1.[服务器端]配置短信内容/模板

* 填写你的验证码短信内容或模板标示符

如果你使用的是内容短信(如云片网络,Luosimao)，则使用或修改'verifySmsContent'的值。
配置文件config/laravel-sms.php:
```php
    'verifySmsContent' => '【填写签名】亲爱的用户，您的验证码是%s。有效期为%s分钟，请尽快验证'
```

如果你使用模板短信(如云通讯,SubMail)，需要到相应代理器中填写模板标示符。
配置文件config/phpsms.php:
```php
   'YunTongXun' => [
       //模板标示符
       'verifySmsTemplateId' => 'your template id',
   ]
```

* 修改或自定义发送前检测规则(可选)

配置文件config/laravel-sms.php:
```php
  'use' => 'mobile_required',
  'rules' => [
      'mobile_required' => 'required|zh_mobile',
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

        //json api token
        //PS:如果你使用的是无会话json api，可以这样带上token
        apiToken       : 'user token string...',

        //定义如何获取mobile的值
        mobileSelector : 'input[name="mobile"]',

        //定义手机号的检测规则,当然你还可以到配置文件中自定义你想要的任何规则
        mobileRule     : 'mobile_required',

        //是否请求语音验证码
        voice          : false,

        //定义服务器有消息返回时如何展示，默认为alert
        alertMsg       :  function (msg, type) {
            alert(msg);
        },
     });
  </script>
```
> **注意:**
> 如果你使用Luosimao语音验证码，请在配置文件中'Luosimao'中设置'voiceApikey'。

###3.[服务器端]合法性验证

用户填写验证码并提交表单到服务器时，在你的控制器中需要验证手机号和验证码是否正确，你只需要加上如下代码即可：
```php
   //验证手机验证码
   $validator = Validator::make(Input::all(), [
        'mobile'     => 'required|confirm_mobile_not_change',
        'verifyCode' => 'required|verify_code|confirm_mobile_rule:mobile_required',
        //more...
   ]);
   if ($validator->fails()) {
       //验证失败后建议清空存储的短信发送信息，防止用户重复试错
       \SmsManager::forgetSentInfo();
       return redirect()->back()->withErrors($validator);
   }
```

PS:
* `confirm_mobile_not_change` 验证用户手机号是否合法。
* `verify_code` 验证验证码是否合法(验证码是否正确，是否超时无效)。
* `confirm_mobile_rule:{$mobileRule}` 检测是否为非法请求，第一个值为手机号检测规则，必须和你在浏览器端js插件中填写的mobileRule的值一致。

**请在语言包validation.php中做好翻译**

#无会话支持

###1. 请求地址
* 短信:
scheme://your-domain.com/sms/verify-code

* 语音:
scheme://your-domain.com/sms/voice-verify

###2. 请求参数
| 参数名  |  必填    | 说明    | 示例      |
| -----  | :-----: | :------: | :-------: |
| mobile | 是      | 手机号码  | `18280......` |
| mobileRule | 否  | 手机号检测规则，默认`mobile_required` | `mobile_required` |
| seconds | 是     | 请求间隔(秒)，默认`60` | `60` |
| token   | 是     | 唯一标识符 |  |

###3. 服务端验证
* step1. 实现存储器:

实现一个接口为`Toplan\Sms\Storage`的存储器，
并在config/laravel-sms.php中配置存储器。
```php
'storage' => 'Your\Namespace\SomeStorage',
```

* step2. 给每个验证规则后加上参数`$token`:
```php
   $token = $request->input('token');
   $validator = Validator::make(Input::all(), [
        'mobile'     => "required|confirm_mobile_not_change:$token",
        'verifyCode' => "required|verify_code:$token|confirm_mobile_rule:mobile_required,$token",
        //more...
   ]);
   if ($validator->fails()) {
       //验证失败后建议清空存储的短信发送信息，防止用户重复试错
       \SmsManager::forgetSentInfo($token);
       return redirect()->back()->withErrors($validator);
   }
```

#更多

###1. 自定义代理器
详情请参看[phpsms](https://github.com/toplan/phpsms#自定义代理器)

###2. 自定义Mobile验证规则

* 定义规则
```php
//方式1:
\SmsManager::storeMobileRule('required|zh_mobile|min:13');
//rule的name默认为当前uri

//方式2:
\SmsManager::storeMobileRule([
    'rule' => 'required|zh_mobile|min:13',//必须
    'token' => '...',//可选，用于无会话api,
    'name' => '...'//可选，给自定义rule取别名，默认为当前uri
]);
```

> 存储的自定义规则访问example.com/sms/info查看。

* 删除规则
```php
\SmsManager::forgetMobileRule([
    'name' => '...',//必填
    'token' => '...'//可选，用于无会话api,
]);
```

* 验证规则
```php
$rule = CUSTOM_RULE;//注意$rule的使用!!!
$token = $request->input('token', null);
$validator = Validator::make($request->all(), [
    ...
    'verifyCode' => "required|verify_code:$token|confirm_mobile_rule:$rule,$token",
    ...
]);
```

#License

MIT
