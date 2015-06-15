# laravel-sms for laravel 5

laravel-sms特点:

1. 数据库记录/管理短信数据及其发送情况。
2. 兼容模板短信和内容短信。
3. [支持短信队列](https://github.com/toplan/laravel-sms#短信队列)。
4. 集成[验证码短信发送/校验模块](https://github.com/toplan/laravel-sms#验证码短信模块)，
   从此告别重复写验证码短信发送和验证码校验。
5. 集成第三方短信服务商，[欢迎贡献更多的代理器](https://github.com/toplan/laravel-sms#开源贡献)。
   目前支持的第三方平台有：
   * [云通讯](http://www.yuntongxun.com)
   * [云片网络](http://www.yunpian.com)
6. [备用代理器机制](https://github.com/toplan/laravel-sms#备用代理器机制)。即:如果用一个服务商发送短信失败，将会自动尝试通过预先设置的备用服务商发送。

##安装
在项目根目录下运行如下composer命令:
```php
   composer require 'toplan/laravel-sms:dev-master'
```

##快速上手

####1.注册服务提供器

在config/app.php文件中providers数组里加入：
```php
   'Toplan\Sms\SmsManagerServiceProvider'
```

在config/app.php文件中的aliases数组里加入
```php
   'SmsManager' => 'Toplan\Sms\Facades\SmsManager'
```

####2.migration生成 & 参数配置

   请先运行如下命令生成配置文件和migration文件：
```php
   php artisan vendor:publish
```

   在数据库中生成sms表：
```php
   php artisan migrate
```

   在config/laravel-sms.php中修改配置。

   如果你使用的是云片，请在数组'YunPian'中按照提示填写配置信息
```php
   'YunPian' => [
        ...
        'apikey' => 'your api key',
   ]
```
   如果你使用的是云通讯，请在数组'YunTongXun'中按照提示填写配置信息
```php
   'YunTongXun' => [
       ...
       'accountSid' => 'your account sid',
       'accountToken' => 'your auth token',
       'appId' => 'your app id',
   ]
```

####3.Enjoy it! 使用Sms模型发送短信

  在控制器中发送模板/内容短信，如：
```php
  //只希望使用模板方式发送短信,如你使用的服务商是云通讯
  Toplan\Sms\Sms::make($tempId)->to('1828****349')->data(['12345', 5])->send();
  //只希望使用内容方式放送,如你使用的服务商是云片
  Toplan\Sms\Sms::make()->to('1828****349')->content('【Laravel SMS】亲爱的张三，欢迎访问，祝你工作愉快。')->send();
  //同时确保能通过两种方式发送。这样做的好处是，可以兼顾到任何备用代理器(服务商)！
  Toplan\Sms\Sms::make($tempId)->to('1828****349')->data(['张三'])
                  ->content('【Laravel SMS】亲爱的张三，欢迎访问，祝你工作愉快。')->send();
```

####4.常用的语法糖

   * 发送给谁
```php
   $sms = $sms->to('1828*******');
```

   * 设置模板ID
   如果你只使用了默认代理器，即没有开启备用代理器机制。你只需要设置默认代理器的模板ID:
```php
   //静态方法设置，并返回sms实例
   $sms = Toplan\Sms\Sms::make('20001');//设置默认代理器的模板id
   //或
   $sms = $sms->template('20001');//设置默认代理器的模板id
```

   如果你要开启备用代理器机制，那么需要为默认/备用代理器设置相应模板ID，这样才能保证每个代理器正常使用。
   你可以样设置:
```php
   //静态方法设置，并返回sms实例
   $sms = Toplan\Sms\Sms::make(['YunTongXun' => '20001', 'ABC' => 'xxx', ...]);
   //或
   $sms = $sms->template('YunTongXun', '20001')->template('ABC' => 'xxx');//设置指定服务商的模板id
   //或
   $sms = $sms->template(['YunTongXun' => '20001', 'ABC' => 'xxx', ...]);//一次性设置多个服务商的模板id
```

  * 设置模板短信的模板数据
```php
  $sms = $sms->data([
        'code' => $code,
        'minutes' => $minutes
      ]);//must be array
```

  * 设置内容短信的内容
```php
  $sms = $sms->content('【Laravel SMS】亲爱的张三，欢迎访问，祝你工作愉快。');
```

  * 开启/关闭短信队列
```php
  $sms = $sms->openQueue();//开启队列,短信会在队列中排队
  $sms = $sms->closeQueue();//关闭队列,短信会直接发送
```

  * 发送短信
```php
  //return true or false;
  $result = $sms->send();
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

##验证码短信模块

####1.[浏览器端]请求发送带验证码短信

 该包已经封装好浏览器端的jqury/zepto插件，只需要为发送按钮添加扩展方法即可实现发送短信。
```html
  //js文件在laravel-sms包的js文件夹中，请复制到项目资源目录
  <script src="/assets/js/jquery(zepto).laravel-sms.js"></script>
  <script>
     //为发送按钮添加sms方法,捕获点击事件
     $('#sendVerifySmsButton').sms({
        //定义如何获取mobile的值
        mobileSelector : 'input[name="mobile"]',
        //定义手机号的检测规则,当然你还可以到配置文件中自定义你想要的任何规则
        mobileRule     : 'check_mobile_unique',
        //下次发送短信的等待时间
        seconds        : 60 //单位秒，默认为60
        //定义服务器有消息返回时如何展示，默认为alert
        alertMsg       :  function (msg) {
            alert(msg);
        },
     });
  </script>
```

####2.[服务器端]配置短信内容/模板

在config/laravel-sms.php中先填写你的验证码 短信内容 或 短信模板标示符

如果你使用的是内容短信，则使用或修改'verifySmsContent'的值(如云片网络)
```php
   'verifySmsContent' => 'bla bla...'
```

如果你使用模板短信，需要到相应代理器中填写模板标示符(如云通讯)
```php
   'YunTongXun' => [
       //模板标示符
       'verifySmsTemplateId' => 'your template id',
   ]
```

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
   * mobile_changed 验证的是用户手机号是否合法。
   * verify_code 验证的是验证码是否合法(包括是否正确，是否超时无效)。
   * verify_rule:{$mobileRule} 用于防止非法请求,后面的第一值为手机号检测规则，必须和你在浏览器端js插件中填写的mobileRule的值一致。

   请在语言包中做好翻译。

##备用代理器机制
  如果用一个服务商发送短信失败，将会自动尝试通过备用服务商发送。
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

在agents目录下添加代理器类,并继承Agent抽象类。如果使用到其他api，可以将api文件放入src/lib文件夹中。

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
            //在这里实现发送内容短信，即直接发送内容
            ...
            //切记将发送结果存入到$this->result
            $this->result['success'] = false;//是否发送成功
            $this->result['info'] = $this->config['currentAgentName'] . ':' . '发送结果说明';//发送结果信息说明
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

最后一步，在SmsManager.php中的最后一行添加方法, 至此, 新加代理器成功!
```php
    public function createFooAgent(Array $agentConfig)
    {
        return new FooAgent($agentConfig);
    }
```
