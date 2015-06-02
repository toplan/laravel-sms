# sms-yuntongxun-laravel
云通讯短信发送 for laravel 4

#快速上手
1. 生成sms默认表
```php
   php artisan migrate --package="toplan/sms"
```
   如果你想更改短信表结构和相应model请参看自助开发里面的介绍。
#自助开发
1. 自己配置config
```php
   php artisan config:publish --path='/vendor/toplan/sms/src/Sms/config/' toplan/sms
```
