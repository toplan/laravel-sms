<?php namespace Toplan\Sms;

interface Sender {

    public function template($tempId);

    public function to($mobile);

    public function data(Array $data);

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
