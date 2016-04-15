<?php

namespace Toplan\Sms;

use App\Jobs\Job;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Toplan\PhpSms\Sms;

class SendReminderSms extends Job implements SelfHandling, ShouldQueue
{
    use InteractsWithQueue;

    protected $sms;

    /**
     * Create a new job instance.
     *
     * @param Sms $sms
     */
    public function __construct(Sms $sms)
    {
        $this->sms = $sms;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->sms->send();
    }
}
