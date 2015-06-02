# laravel-sms v1.0 for laravel 4.2
目前支持的第三方平台有：
* [云通讯](http:http://www.yuntongxun.com)

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

4.使用Sms模型发送短信
```php
    Toplan\Sms\Sms::make($tempId)->to('1828****349')->data(['99999', 1])->send();
```

##自助二次开发
1.修改配置文件
```php
   php artisan config:publish --path='/vendor/toplan/sms/src/Sms/config/' toplan/sms
```
   运行以上命令成功后，然后在app/config/package/toplan/sms/config.php中修改配置。

2.修改model
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

