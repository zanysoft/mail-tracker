<?php

namespace ZanySoft\MailTracker\Tests;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Event;
use ZanySoft\MailTracker\Model\SentEmail;
use ZanySoft\MailTracker\RecordBounceJob;
use ZanySoft\MailTracker\RecordDeliveryJob;
use ZanySoft\MailTracker\RecordTrackingJob;
use ZanySoft\MailTracker\RecordComplaintJob;
use ZanySoft\MailTracker\RecordLinkClickJob;
use ZanySoft\MailTracker\Events\ViewEmailEvent;
use ZanySoft\MailTracker\Events\LinkClickedEvent;

class RecordTrackingJobTest extends SetUpTest
{
    /**
     * @test
     */
    public function it_records_views()
    {
        Event::fake();
        $track = \ZanySoft\MailTracker\Model\SentEmail::create([
                'hash' => Str::random(32),
            ]);
        $job = new RecordTrackingJob($track);

        $job->handle();

        Event::assertDispatched(ViewEmailEvent::class, function ($e) use ($track) {
            return $track->id == $e->sent_email->id;
        });
        $this->assertDatabaseHas('sent_emails', [
                'id' => $track->id,
                'opens' => 1,
            ]);
    }
}
