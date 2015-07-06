# laravel-sms for laravel 5

*使用场景*

1. 发送短信验证码。
2. 发送信息通知短信(如：订单通知，发货通知，上课通知...)。
3. 特殊情况下用户收不到短信？ laravel-sms提倡通过备用代理器机制使用两个及两个以上服务商。

*该包特性*

1. 数据库记录/管理短信数据及其发送情况。
2. 兼容模板短信和内容短信。
3. [支持短信队列](https://github.com/toplan/laravel-sms#短信队列)。
4. [备用代理器(服务商)机制](https://github.com/toplan/laravel-sms#备用代理器机制)。即:如果用一个服务商发送短信失败，将会自动尝试通过预先设置的备用服务商发送。
5. 集成[验证码短信发送/校验模块](https://github.com/toplan/laravel-sms#验证码短信模块)，分分钟搞定验证码短信发送以及手机号/验证码校验，
   从此告别重复写验证码短信发送与校验的历史。
6. 集成第三方短信服务商，[欢迎提供更多的服务商](https://github.com/toplan/laravel-sms#开源贡献)。
   目前支持的服务商有：
   * [Luosimao](http://luosimao.com)
   * [云片网络](http://www.yunpian.com)
   * [容联·云通讯](http://www.yuntongxun.com)
   * [SUBMAIL](http://submail.cn)
   * [云之讯](http://www.ucpaas.com/)

##安装
在项目根目录下运行如下composer命令:
```php
   composer require 'toplan/laravel-sms:dev-master'
```

##快速上手

####1.注册服务提供器

在config/app.php文件中providers数组里加入：
```php
   //laravel 5.0.*
   'Toplan\Sms\SmsManagerServiceProvider'
   //laravel 5.1.*
   Toplan\Sms\SmsManagerServiceProvider::class
```

在config/app.php文件中的aliases数组里加入
```php
   //laravel 5.0.*
   'SmsManager' => 'Toplan\Sms\Facades\SmsManager'
   //laravel 5.1.*
   'SmsManager' => Toplan\Sms\Facades\SmsManager::class
```

####2.migration生成 & 参数配置

   * 请先运行如下命令生成配置文件和migration文件
```php
   php artisan vendor:publish
```

   * 在数据库中生成sms表
```php
   php artisan migrate
```

   * 设置默认代理器(服务商)

   请在config/laravel-sms.php中设置默认代理服务商，默认为'Luosimao'。
```php
   'agent' => 'Luosimao',
```

   * 配置代理服务商的相关参数

   在config/laravel-sms.php中，找到你想要使用的代理器，并填写好配置信息。

>  如果你使用的是Luosimao，请在数组'Luosimao'中按照提示填写配置信息
>  ```php
>     'Luosimao' => [
>          ...
>          'apikey' => 'your api key',
>     ]
>  ```

   更多的服务商配置就不详说了，请到配置文件中查看并按提示修改相应代理服务商的配置。

####3.Enjoy it! 使用Sms模型发送短信

  在控制器中发送触发短信，如：
```php
  //只希望使用模板方式发送短信,可以不设置内容content (如云通讯,Submail)
  Toplan\Sms\Sms::make($tempId)->to('1828****349')->data(['12345', 5])->send();

  //只希望使用内容方式放送,可以不设置模板id和模板数据data (如云片,luosimao)
  Toplan\Sms\Sms::make()->to('1828****349')->content('【Laravel SMS】亲爱的张三，欢迎访问，祝你工作愉快。')->send();

  //同时确保能通过模板和内容方式发送。这样做的好处是，可以兼顾到各种代理器(服务商)！
  Toplan\Sms\Sms::make([
      'YunTongXun' => '123',
      'SubMail'    => '123'
  ])
  ->to('1828****349')
  ->data(['张三'])
  ->content('【签名】亲爱的张三，欢迎访问，祝你工作愉快。')
  ->send();
```

####4.常用的语法糖

   * 发送给谁
```php
   $sms = $sms->to('1828*******');
   $sms = $sms->to(['1828*******', '1828*******', ...]);//多个目标号码
```

   * 设置模板ID

如果你只使用了默认代理器，即没有开启备用代理器机制。你只需要设置默认代理器的模板ID:
```php
   //静态方法设置，并返回sms实例
   $sms = Toplan\Sms\Sms::make('20001');
   //或
   $sms = $sms->template('20001');
```

如果你要开启备用代理器机制，那么需要为只支持模板短信默认/备用代理器设置相应模板ID，这样才能保证这些代理器正常使用。可以这样设置:
```php
   //静态方法设置，并返回sms实例
   $sms = Toplan\Sms\Sms::make(['YunTongXun' => '20001', 'SubMail' => 'xxx', ...]);
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
      ]);//must be array
```

  * 设置内容短信的内容

  有些服务商(如YunPian,Luosimao)只支持内容短信(即直接发送短信内容)，不支持模板，那么就需要设置短信内容。
```php
  $sms = $sms->content('【签名】亲爱的张三，您的订单号是281xxxx，祝你购物愉快。');
```

  * 临时开启/关闭短信队列
```php
  $sms = $sms->openQueue();//开启队列,短信会在队列中排队
  $sms = $sms->closeQueue();//关闭队列,短信会直接发送
```

  * 发送短信
```php
  $sms->send();//return true or false
```

##短信队列

  在config/laravel-sms中修改配置
```php
   //开启队列为true, 关闭队列为false
   'smsSendQueue' => true,
```
  如果你开启了队列，需要运行如下命名监听队列
```php
   php artisan queue:listen
```

##备用代理器机制
  如果用一个服务商发送短信失败(如：欠费、频繁发送、发送次数上限、内容重复...)，将会自动尝试通过备用服务商发送。
  在config/laravel-sms.php中配置备用代理器
```php
  'alternate' => [
      //关闭备用代理器机制为false,打开为true
      'enable' => false,
      //备用代理器组，排名分先后，越在前面的代理器会优先使用
      //example: ['YunPian', ...]
      'agents' => []
  ],
```
  其中agents中如果有多个值，如：A,B,C。
  那么当默认代理器发送失败时，会自动启用A代理器，若A代理器发送失败，则会自动启用B，依次类推直到最后一个备用代理器。

##验证码短信模块

可以直接访问example.com/sms/info查看该模块是否可用，并可在该页面里观察验证码短信发送数据，方便你进行调试。

####1.[浏览器端]请求发送带验证码短信

该包已经封装好浏览器端的jquery/zepto插件，只需要为发送按钮添加扩展方法即可实现发送短信。
```html
  //js文件在laravel-sms包的js文件夹中，请复制到项目资源目录
  <script src="/assets/js/jquery(zepto).laravel-sms.js"></script>
  <script>
     $('#sendVerifySmsButton').sms({
        //token value
        token          : "{{csrf_token()}}",
        //定义如何获取mobile的值
        mobileSelector : 'input[name="mobile"]',
        //定义手机号的检测规则,当然你还可以到配置文件中自定义你想要的任何规则
        mobileRule     : 'check_mobile_unique',
        //定义服务器有消息返回时如何展示，默认为alert
        alertMsg       :  function (msg) {
            alert(msg);
        },
     });
  </script>
```

####2.[服务器端]配置短信内容/模板

配置文件: config/laravel-sms.php

* 填写你的验证码短信内容或模板标示符

> 如果你使用的是内容短信(如云片网络,Luosimao)，则使用或修改'verifySmsContent'的值：
> ```php
>    'verifySmsContent' => '【填写签名】亲爱的用户，您的验证码是%s。有效期为%s分钟，请尽快验证'
> ```

> 如果你使用模板短信(如云通讯,SubMail)，需要到相应代理器中填写模板标示符：
> ```php
>    'YunTongXun' => [
>        //模板标示符
>        'verifySmsTemplateId' => 'your template id',
>    ]
> ```

* 修改或自定义发送前检测规则

> ```php
>    'rules' => [
>        //唯一性检测规则
>        'check_mobile_unique' => 'unique:users,mobile',//适用于注册
>        //存在性检测规则
>        'check_mobile_exists' => 'exists:users',//适用于找回密码和系统内业务验证
>    ]
> ```


####3.[服务器端]合法性验证

用户填写验证码并提交表单到服务器时，在你的控制器中需要验证手机号和验证码是否正确，你只需要加上如下代码即可：
```php
   //验证手机验证码
   $validator = Validator::make(Input::all(), [
        'mobile'     => 'required|mobile_changed',
        'verifyCode' => 'required|verify_code|verify_rule:check_mobile_unique',
        //more...
   ]);
   if ($validator->fails()) {
       //验证失败的话需要清除session数据，防止用户多次试错
       SmsManager::forgetSmsDataFromSession();
       return redirect()->back()->withErrors($validator);
   }
```
   PS:
   * `mobile_changed` 验证用户手机号是否合法。
   * `verify_code` 验证验证码是否合法(验证码是否正确，是否超时无效)。
   * `verify_rule:{$mobileRule}` 检测是否为非法请求，第一值为手机号检测规则，必须和你在浏览器端js插件中填写的mobileRule的值一致。

   请在语言包中做好翻译。

##自助二次开发

####1.自定义Model

   继承model类(Toplan\Sms\Sms)
```php
  namespace App\Models;
  class MySmsModel extends Toplan\Sms\Sms {
        //override
        public function send()
        {
            //发送入口
        }
        //override
        public function sendProcess()
        {
            //发送过程
        }
        //more functions...
  }
```
 修改model类后需要在配置文件中，修改key为'smsModel'的值：
```php
   'smsModel' => 'App\Models\MySmsModel',
```

##开源贡献

欢迎贡献更多的代理器，这样就能支持更多第三方服务商的发送接口。请注意命名规范，Foo为代理器(服务商)名称。

配置项加入到src/config/laravel-sms.php中：

```php
   'Foo' => [
        'verifySmsTemplateId' => '',

        'isResendFailedSmsInQueue' => false,

        'xxx' => 'some info',
        ...
   ]
```

在agents目录下添加代理器类(注意类名为FooAgent),并继承Agent抽象类。如果使用到其他api，可以将api文件放入src/lib文件夹中。

```php
   namespace Toplan\Sms;
   class FooAgent extends Agent {
        //override
        //发送短信一级入口
        public function sendSms($tempId, $to, Array $data, $content){
           //在这个方法中调用二级入口
           //根据你使用的服务商的接口选择调用哪个方式发送短信
           $this->sendContentSms($to, $content);
           $this->sendTemplateSms($tempId, $to, Array $data);
        }

        //override
        //发送短信二级入口：发送内容短信
        public function sendContentSms($to, $content)
        {
            //通过$this->config['key'],获取配置文件中的参数
            $x = $this->config['xxx'];
            $x = $this->xxx;//也可以这样获取配置参数
            //在这里实现发送内容短信，即直接发送内容
            ...
            //切记将发送结果存入到$this->result
            $this->result['success'] = false;//是否发送成功
            $this->result['info'] = $this->currentAgentName . ':' . '发送结果说明';//发送结果信息说明
            $this->result['code'] = $code;//发送结果代码
        }

        //override
        //发送短信二级入口：发送模板短信
        public function sendTemplateSms($tempId, $to, Array $data)
        {
            //同上...
        }
   }
```
至此, 新加代理器成功!

##License

MIT
