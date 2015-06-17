<?php namespace Toplan\Sms;

interface Sender {

    public function content($content);

    public function template($agentName, $tempId);

    public function to($mobile);

    public function data(Array $data);

    /**
     * sms send entry
     * @return mixed
     */
    public function send();

    /**
     * sms send process
     * @return mixed
     */
    public function sendProcess();

}
