<?php namespace Toplan\Sms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use \Queue;
use \Validator;
use \SmsManager;

class Sms extends Model implements Sender{

    /**
     * table name
     * @var string
     */
    protected $table = "sms";

    protected $guarded = ['id'];

    protected $dates = ['deleted_at','updated_at','created_at'];

    /**
     * support soft delete
     */
    use SoftDeletes;
    protected $softDelete = true;

    /**
     * 短信发送代理器
     * @var
     */
    protected $agent;

    /**
     * data rules
     * @var array
     */
    public $rules =  [
            'to'      => 'required',
        ];

    /**
     * create instance
     */
    public function __construct()
    {
        $this->agent = SmsManager::agent();
    }

    /**
     * create a instance of sms
     * @param $tempId
     *
     * @return Sms
     */
    public static function make($tempId = '')
    {
        $sms = new self;
        $sms->temp_id = $tempId;
        return $sms;
    }

    /**
     * send sms by queue
     */
    public function openQueue()
    {
        $this->agent->openQueue();
        return $this;
    }

    /**
     * send directly
     */
    public function closeQueue()
    {
        $this->agent->closeQueue();
        return $this;
    }

    /**
     * set sms content
     * @param $content
     *
     * @return $this
     */
    public function content($content)
    {
        $this->content = $content;
        return $this;
    }

    /**
     * set template id
     * @param $tempId
     *
     * @return $this
     */
    public function template($tempId)
    {
        $this->temp_id = $tempId;
        return $this;
    }

    /**
     * set the mobile number
     * @param $mobile
     *
     * @return $this
     */
    public function to($mobile)
    {
        $this->to = $mobile;
        return $this;
    }

    /**
     * set data
     * @param array $data
     *
     * @return $this
     */
    public function data(Array $data)
    {
        $this->data = json_encode($data);
        return $this;
    }

    /**
     * 发送短信入口
     * @return bool|mixed
     */
    public function send()
    {
        $validator = Validator::make([
            'temp_id' => $this->getTempId(),
            'to'      => $this->getTo(),
            'data'    => $this->getData(),
            'content' => $this->getContent()
        ], $this->rules);
        if ( ! $validator->fails()) {
            if ( ! $this->created_at) {
                $this->save();
            }
            if ($this->agent->isPushToQueue()) {
                $data = [
                    'smsId'    => $this->id,
                    'isResend' => $this->agent->isResendFailedSmsInQueue(),
                ];
                Queue::push($this->agent->getWorkerName(), $data);
            } else {
                return $this->sendProcess();
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * 短信发送过程 send process
     * @return bool
     */
    public function sendProcess()
    {
        $result = $this->agent->sms($this->getTempId(), $this->getTo(), $this->getData(true), $this->getContent());
        if ($result['success']) {
            $this->sent_time = time();
            $this->result_info = $result['info'];
            $this->update();
        } else {
            $this->last_fail_time = time();
            $this->fail_times += 1;
            $this->result_info = $result['info'];
            $this->update();
        }
        return $result['success'];
    }

    /**
     * get template id
     * @return mixed
     */
    public function getTempId()
    {
        return $this->temp_id;
    }

    /**
     * get mobile
     * @return mixed
     */
    public function getTo()
    {
        return $this->to;
    }

    /**
     * get data
     * @param bool $getArray
     *
     * @return mixed
     */
    public function getData($getArray = false)
    {
        return $getArray ? json_decode($this->data) : $this->data;
    }

    /**
     * get content
     * @return mixed
     */
    public function getContent()
    {
        return $this->content;
    }

}
