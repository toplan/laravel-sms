# laravel-sms v1.0 for laravel 4.2
目前支持的第三方平台有：
* [云通讯](http:http://www.yuntongxun.com)

##安装
在项目根目录下运行如下composer命令:
```php
   composer require 'toplan/laravel-sms:dev-master'
```

##快速上手
1.生成sms默认表
```php
   php artisan migrate --package="toplan/sms"
```
   如果你想更改短信表结构和相应model请参看自助开发里面的介绍。

2.在app/config/app.php文件中providers数组里加入：
```php
   'Toplan\Sms\SmsManagerServiceProvider'
```

3.在app/config/app.php文件中的aliases数组里加入
```php
   'SmsManager' => 'Toplan\Sms\Facades\SmsManager',
```
4.参数配置
   请先运行如下命令生成配置文件：
```php
   php artisan config:publish --path='/vendor/toplan/sms/src/Sms/config/' toplan/sms
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

5.使用Sms模型发送短信
```php
    Toplan\Sms\Sms::make($tempId)->to('1828****349')->data(['99999', 1])->send();
```


##自助二次开发
1.继承model
   请继承model类(Toplan\Sms\Sms)
```php
  class MySmsModel extends Toplan\Sms\Sms {

        //override
        public function sendProcess()
        {
            //发送过程
        }

  }
```

