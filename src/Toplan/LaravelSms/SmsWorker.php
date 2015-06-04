<?php namespace Toplan\Sms;

class SmsWorker {

    public function fire( $job ,  $data ){
        $id       = $data['smsId'];
        $isResend = $data['isResend'];
        $sms = Sms::find($id);
        if ( is_null($sms) ) {
            $job->delete();
            exit("--don`t find sms (id:$id)--");
        }
        if ($sms->sendProcess()) {
            //sent success
            $job->delete();
        } else {
            //sent failed
            if ( ! $isResend) {
                //don`t resent sms
                $job->delete();
            }
        }
    }

}