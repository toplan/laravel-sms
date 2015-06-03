<?php namespace Toplan\Sms;

interface Sender {

    /**
     * 发送短信入口
     * @return mixed
     */
    public function send();

    /**
     * 发送过程
     * @return mixed
     */
    public function sendProcess();

}
