# Laravel Sms v2.0

> laravel-sms v2.0基于[phpsms](https://github.com/toplan/phpsms)二次开发。
> laravel-sms v2.0请求负载均衡由[task-balancer](https://github.com/toplan/task-balancer)提供。

**使用场景**

1. 发送短信验证码。
2. 发送信息通知短信(如：订单通知，发货通知，上课通知...)。
3. 特殊情况下用户收不到短信？ v2.0提倡通过按权重均衡分发请求且采用备用代理器机制。

**该包特性**

1. 数据库记录/管理短信数据及其发送情况(可选)。
2. 兼容模板短信和内容短信。
3. [短信队列](https://github.com/toplan/laravel-sms#短信队列)。
4. 请求负载均衡。
5. 备用代理器。
6. 集成[验证码短信发送/校验模块](https://github.com/toplan/laravel-sms#验证码短信模块)，分分钟搞定验证码短信发送以及手机号/验证码校验，
   从此告别重复写验证码短信发送与校验的历史。
7. 支持语音验证码，使用方法见特性6。
8. 集成如下第三方短信服务商，你也可[自定义代理器](https://github.com/toplan/laravel-sms#自定义代理器)。

| 服务商 | 模板短信 | 内容短信 | 语音验证码 | 最低消费  |  最低消费单价 |
| ----- | :-----: | :-----: | :------: | :-------: | :-----: |
| [Luosimao](http://luosimao.com)        | no  | yes |  yes    |￥850(1万条) |￥0.085/条|
| [云片网络](http://www.yunpian.com)       | no | yes  | yes    |￥55(1千条)  |￥0.055/条|
| [容联·云通讯](http://www.yuntongxun.com) | yes | no  | yes    |充值￥500    |￥0.055/条|
| [SUBMAIL](http://submail.cn)           | yes | no  | no      |￥100(1千条) |￥0.100/条|
| [云之讯](http://www.ucpaas.com/)        | yes | no  | yes     |            |￥0.050/条|

##安装
在项目根目录下运行如下composer命令:
```php
   //安装稳定版本
   composer require 'toplan/laravel-sms:~2.0.0'

   //安装开发中版本
   composer require 'toplan/laravel-sms:dev-master'
```

##快速上手

####1.注册服务提供器

在config/app.php文件中providers数组里加入：
```php
   Toplan\PhpSms\Sms::class,
   Toplan\Sms\SmsManagerServiceProvider::class,
```

在config/app.php文件中的aliases数组里加入
```php
   'SmsManager' => Toplan\Sms\Facades\SmsManager::class,
   'PhpSms' => Toplan\PhpSms\Facades\Sms::class,
```

####2.migration生成 & 参数配置

   * 请先运行如下命令生成配置文件和migration文件
```php
   php artisan vendor:publish
```

   * 在数据库中生成sms表(可选)
```php
   php artisan migrate
```

   * 设置默认代理器(服务商)

   请在config/phpsms.php中设置代理服务商。
```php
   'enable' => [
        //被使用概率为2/3
        'Luosimao' => '20',

        //被使用概率为1/3，且为备用代理器
        'YunPian' => '10 backup',
   ];
```

   * 配置代理服务商的相关参数

   在config/phpsms.php中，找到你想要使用的代理器，并填写好配置信息。

>  如果你使用的是Luosimao，请在数组'Luosimao'中按照提示填写配置信息
>  ```php
>     'Luosimao' => [
>          ...
>          'apikey' => 'your api key',
>     ]
>  ```

   更多的服务商配置就不详说了，请到配置文件中查看并按提示修改相应代理服务商的配置。

####3.Enjoy it!

  在控制器中发送触发短信，如：
```php
  //只希望使用模板方式发送短信,可以不设置内容content (如云通讯,Submail)
  PhpSms::make($tempId)->to('1828****349')->data(['12345', 5])->send();

  //只希望使用内容方式放送,可以不设置模板id和模板数据data (如云片,luosimao)
  PhpSms::make()->to('1828****349')->content('【Laravel SMS】亲爱的张三，欢迎访问，祝你工作愉快。')->send();

  //同时确保能通过模板和内容方式发送。这样做的好处是，可以兼顾到各种代理器(服务商)！
  PhpSms::make([
      'YunTongXun' => '123',
      'SubMail'    => '123'
  ])
  ->to('1828****349')
  ->data(['张三'])
  ->content('【签名】亲爱的张三，欢迎访问，祝你工作愉快。')
  ->send();
```

####4.常用的语法糖

> 更加详情的用法可以参看[phpsms](https://github.com/toplan/phpsms)

   * 发送给谁
```php
   $sms = $sms->to('1828*******');
```

   * 设置模板ID

如果你只使用了默认代理器，即没有开启备用代理器机制。你只需要设置默认代理器的模板ID:
```php
   //静态方法设置，并返回sms实例
   $sms = PhpSms::make('20001');
   //或
   $sms = $sms->template('20001');
```

如果你要开启备用代理器机制，那么需要为只支持模板短信默认/备用代理器设置相应模板ID，这样才能保证这些代理器正常使用。可以这样设置:
```php
   //静态方法设置，并返回sms实例
   $sms = PhpSms::make(['YunTongXun' => '20001', 'SubMail' => 'xxx', ...]);
   //设置指定服务商的模板id
   $sms = $sms->template('YunTongXun', '20001')->template('SubMail' => 'xxx');
   //一次性设置多个服务商的模板id
   $sms = $sms->template(['YunTongXun' => '20001', 'SubMail' => 'xxx', ...]);
```

  * 设置模板短信的模板数据
```php
  $sms = $sms->data([
        'code' => $code,
        'minutes' => $minutes
      ]);
```

  * 设置内容短信的内容

  有些服务商(如YunPian,Luosimao)只支持内容短信(即直接发送短信内容)，不支持模板，那么就需要设置短信内容。
```php
  $sms = $sms->content('【签名】亲爱的张三，您的订单号是281xxxx，祝你购物愉快。');
```

  * 发送短信
```php
  $sms->send();
```

##短信队列

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


##验证码短信模块

可以直接访问example.com/sms/info查看该模块是否可用，并可在该页面里观察验证码短信发送数据，方便你进行调试。

####1.[浏览器端]请求发送带验证码短信

该包已经封装好浏览器端的插件(兼容jquery/zepto)，只需要为发送按钮添加扩展方法即可实现发送短信。
```html
  //js文件在laravel-sms包的js文件夹中，请复制到项目资源目录
  <script src="/path/to/laravel-sms.js"></script>
  <script>
     $('#sendVerifySmsButton').sms({
        //token value
        token          : "{{csrf_token()}}",
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

####2.[服务器端]配置短信内容/模板


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

* 修改或自定义发送前检测规则

配置文件config/laravel-sms.php:
```php
  'rules' => [
      //唯一性检测规则
      'check_mobile_unique' => 'unique:users,mobile',//适用于注册
      //存在性检测规则
      'check_mobile_exists' => 'exists:users',//适用于找回密码和系统内业务验证
  ]
```


####3.[服务器端]合法性验证

用户填写验证码并提交表单到服务器时，在你的控制器中需要验证手机号和验证码是否正确，你只需要加上如下代码即可：
```php
   //验证手机验证码
   $validator = Validator::make(Input::all(), [
        'mobile'     => 'required|config_mobile_not_change',
        'verifyCode' => 'required|verify_code|confirm_mobile_rule:check_mobile_unique',
        //more...
   ]);
   if ($validator->fails()) {
       //验证失败的话需要清除session数据，防止用户多次试错
       \SmsManager::forgetSentInfo();
       return redirect()->back()->withErrors($validator);
   }
```

PS:
* `confirm_mobile_not_change` 验证用户手机号是否合法。
* `verify_code` 验证验证码是否合法(验证码是否正确，是否超时无效)。
* `confirm_mobile_rule:{$mobileRule}` 检测是否为非法请求，第一个值为手机号检测规则，必须和你在浏览器端js插件中填写的mobileRule的值一致。

**请在语言包validation.php中做好翻译**

##自定义代理器

详情的请参看[phpsms](https://github.com/toplan/phpsms)

##License

MIT
