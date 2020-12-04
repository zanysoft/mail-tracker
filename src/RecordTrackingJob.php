<?php

namespace ZanySoft\MailTracker;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Event;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use ZanySoft\MailTracker\Model\SentEmail;
use ZanySoft\MailTracker\Events\ViewEmailEvent;
use ZanySoft\MailTracker\Events\LinkClickedEvent;
use ZanySoft\MailTracker\Model\SentEmailUrlClicked;
use ZanySoft\MailTracker\Events\EmailDeliveredEvent;

class RecordTrackingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $sentEmail;

    public function __construct($sentEmail)
    {
        $this->sentEmail = $sentEmail;
    }

    public function retryUntil()
    {
        return now()->addDays(5);
    }

    public function handle()
    {
        $this->sentEmail->opens++;
        $this->sentEmail->save();
        Event::dispatch(new ViewEmailEvent($this->sentEmail));
    }
}
