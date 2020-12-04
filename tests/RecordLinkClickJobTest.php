<?php

namespace ZanySoft\MailTracker\Tests;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Event;
use ZanySoft\MailTracker\Model\SentEmail;
use ZanySoft\MailTracker\RecordBounceJob;
use ZanySoft\MailTracker\RecordDeliveryJob;
use ZanySoft\MailTracker\RecordComplaintJob;
use ZanySoft\MailTracker\RecordLinkClickJob;
use ZanySoft\MailTracker\Events\LinkClickedEvent;

class RecordLinkClickJobTest extends SetUpTest
{
    /**
     * @test
     */
    public function it_records_clicks_to_links()
    {
        Event::fake();
        $track = \ZanySoft\MailTracker\Model\SentEmail::create([
                'hash' => Str::random(32),
            ]);
        $clicks = $track->clicks;
        $clicks++;
        $redirect = 'http://'.Str::random(15).'.com/'.Str::random(10).'/'.Str::random(10).'/'.rand(0, 100).'/'.rand(0, 100).'?page='.rand(0, 100).'&x='.Str::random(32);
        $job = new RecordLinkClickJob($track, $redirect);

        $job->handle();

        Event::assertDispatched(LinkClickedEvent::class, function ($e) use ($track) {
            return $track->id == $e->sent_email->id;
        });
        $this->assertDatabaseHas('sent_emails_url_clicked', [
                'url' => $redirect,
                'clicks' => 1,
            ]);
    }
}
