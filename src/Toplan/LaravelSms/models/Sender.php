<?php namespace Toplan\Sms;

interface Sender {

    /**
     * receiver
     * @param $mobile
     *
     * @return mixed
     */
    public function to($mobile);

    /**
     * set text content for content sms
     * @param $content
     *
     * @return mixed
     */
    public function content($content);

    /**
     * set template id
     * @param $agentName
     * @param $tempId
     *
     * @return mixed
     */
    public function template($agentName, $tempId);

    /**
     * set template data
     * @param array $data
     *
     * @return mixed
     */
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
