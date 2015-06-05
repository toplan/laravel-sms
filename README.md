# laravel-sms v1.0 for laravel 4.2

特点

1. 支持数据库记录短信发送情况。
2. 支持短信队列。
3. 集成验证码短信发送模块，告别写验证码短信发送和验证代码。
3. 集成第三方短信发送服务，目前支持的第三方平台有：
  * [云通讯](http:http://www.yuntongxun.com)

##安装
在项目根目录下运行如下composer命令:
```php
   composer require 'toplan/laravel-sms:dev-master'
```

##快速上手
####1.在数据库中生成sms表

   存储短信发送记录，方便管理。
```php
   php artisan migrate --package="toplan/laravel-sms"
```
   如果你想更改短信表结构和相应model请参看自助开发里面的介绍。

####2.注册服务提供器

在app/config/app.php文件中providers数组里加入：
```php
   'Toplan\Sms\SmsManagerServiceProvider'
```

 在app/config/app.php文件中的aliases数组里加入
```php
   'SmsManager' => 'Toplan\Sms\Facades\SmsManager',
```
####3.参数配置
   请先运行如下命令生成配置文件：
```php
   php artisan config:publish toplan/laravel-sms
```
   运行以上命令成功后，然后在app/config/package/toplan/sms/config.php中修改配置。
   如果你使用的是云通讯，请在数组'YunTongXun'中按照提示填写配置信息
```php
   //主帐号,对应开官网发者主账号下的 ACCOUNT SID
   'accountSid' => 'your account sid',

   //主帐号令牌,对应官网开发者主账号下的 AUTH TOKEN
   'accountToken' => 'your auth token',

   //应用Id，在官网应用列表中点击应用，对应应用详情中的APP ID
   //在开发调试的时候，可以使用官网自动为您分配的测试Demo的APP ID
   'appId' => 'your app id',

   ...
```

####4.Enjoy it! 使用Sms模型发送短信

  验证是否安装成功：
  在浏览器访问链接example.com/sms/info。如果显示'hello, welcome to laravel-sms'则表示安装成功。

  在控制器中发送模板短信，如：
```php
  Toplan\Sms\Sms::make($tempId)->to('1828****349')->data(['99999', 1])->send();
```


##验证码短信发送模块

####1.[浏览器端]请求发送带验证码短信

 你除了可以自己写验证码发送相关功能外，你也可以使用该包集成的验证码发送模块来发送验证码，使用方法下：
```html
  <script src="/assets/js/jquery.toplan_sms.js"></script>
  <script>
     //为发送按钮添加sms(发送短信)事件
     $('#sendVerifySmsButton').sms({
        //定义如何获取mobile的值
        mobileSelector : 'input[name="mobile"]',
        //定义手机号的检测规则
        //check_mobile_unique可用于注册,check_mobile_exists可用于找回密码
        //当然你还可以到配置文件中自定义你想要的任何规则
        mobileRule     : 'check_mobile_unique',
        //定义服务器有消息返回时，如何展示，默认为alert
        alertMsg       :  function (msg) {
            alert(msg);
        }
     });
  </script>
```

####2.[服务器端]配置模板id

在app/config/packages/toplan/laravel-sms/config.php中先填写你验证码短信模板标示符/ID
```php
   //模板/项目标示符/ID
   'templateIdForVerifySms' => 'your template id',
```

####3.[服务器端]合法性验证

用户填写验证码并提交表单到服务器时，在你的控制器中需要验证手机号和验证码是否正确，你只需要加上如下代码即可：
```php
   //验证手机验证码
   $validator = Validator::make(Input::all(), [
        'mobile'     => 'required|mobile_changed',
        'verifyCode' => 'required|verify_code|verify_rule:check_mobile_unique',
   ]);
   if ($validator->fails()) {
       return Redirect::back()->withInput()->withErrors($validator);
   }
```
   PS:

   mobile_changed 验证的是用户手机号是否合法。

   verify_code 验证的是验证码是否合法(包括是否正确，是否超时无效)。

   verify_rule:{$ruleName} 用于防止非法请求,后面的第一值必须填写你的手机号使用的检测规则。

   请在语言包中做好翻译。

##自助二次开发
####1.自定义Model

   继承model类(Toplan\Sms\Sms)
```php
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

  }
```
 修改model类后需要在配置文件中，修改key为'smsModel'的值，
```php
   'smsModel' => 'Toplan\Sms\Sms',
```
