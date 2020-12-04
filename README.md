# MailTracker

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]
[![Travis][ico-travis]][link-travis]

MailTracker will hook into all outgoing emails from Laravel and inject a tracking code into it. It will also store the rendered email in the database. There is also an interface to view sent emails.

## Install

Via Composer

```bash
$ composer require zanysoft/mail-tracker
```

Publish the config file and migration

```bash
$ php artisan vendor:publish --provider="ZanySoft\MailTracker\MailTrackerServiceProvider"
```

Run the migration

```bash
$ php artisan migrate
```

Note: If you would like to use a different connection to store your models,
you should update the mail-tracker.php config entry `connection` before running the migrations.

## Usage

Once installed, all outgoing mail will be logged to the database. The following config options are available in config/mail-tracker.php:

-   **name**: set your App Name.
-   **inject-pixel**: set to true to inject a tracking pixel into all outgoing html emails.
-   **track-links**: set to true to rewrite all anchor href links to include a tracking link. The link will take the user back to your website which will then redirect them to the final destination after logging the click.
-   **expire-days**: How long in days that an email should be retained in your database. If you are sending a lot of mail, you probably want it to eventually expire. Set it to zero to never purge old emails from the database.
-   **route**: The route information for the tracking URLs. Set the prefix and middlware as desired.
-   **admin-route**: The route information for the admin. Set the prefix and middleware.
-   **admin-template**: The params for the Admin Panel and Views. You can integrate your existing Admin Panel with the MailTracker admin panel.
-   **date-format**: You can define the format to show dates in the Admin Panel.

If you do not wish to have an email tracked, then you can add the `X-No-Track` header to your message. Put any random string into this header to prevent the tracking from occurring. The header will be removed from the email prior to being sent.

```php
\Mail::send('email.test', [], function ($message) {
    // ... other settings here
    $message->getHeaders()->addTextHeader('X-No-Track',Str::random(10));
});
```

## Events

When an email is sent, viewed, or a link is clicked, its tracking information is counted in the database using the ZanySoft\MailTracker\Model\SentEmail model. This processing is done via dispatched jobs to the default queue in order to prevent the database from being overwhelmed is an email blast situation.

You may want to do additional processing on these events, so an event is fired in these cases:

-   ZanySoft\MailTracker\Events\EmailSentEvent
-   ZanySoft\MailTracker\Events\ViewEmailEvent
-   ZanySoft\MailTracker\Events\LinkClickedEvent

If you are using the Amazon SNS notification system, these events are fired so you can do additional processing.

-   ZanySoft\MailTracker\Events\EmailDeliveredEvent (when you received a "message delivered" event, you may want to mark the email as "good" or "delivered" in your database)
-   ZanySoft\MailTracker\Events\ComplaintMessageEvent (when you received a complaint, ex: marked as "spam", you may want to remove the email from your database)
-   ZanySoft\MailTracker\Events\PermanentBouncedMessageEvent (when you receive a permanent bounce, you may want to mark the email as bad or remove it from your database)

To install an event listener, you will want to create a file like the following:

```php
<?php

namespace App\Listeners;

use ZanySoft\MailTracker\Events\ViewEmailEvent;

class EmailViewed
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  ViewEmailEvent  $event
     * @return void
     */
    public function handle(ViewEmailEvent $event)
    {
        // Access the model using $event->sent_email...
    }
}
```

```php
<?php

namespace App\Listeners;

use ZanySoft\MailTracker\Events\PermanentBouncedMessageEvent;

class BouncedEmail
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  PermanentBouncedMessageEvent  $event
     * @return void
     */
    public function handle(PermanentBouncedMessageEvent $event)
    {
        // Access the email address using $event->email_address...
    }
}
```

Then you must register the events you want to act on in your \App\Providers\EventServiceProvider \$listen array:

```php
/**
 * The event listener mappings for the application.
 *
 * @var array
 */
protected $listen = [
    'ZanySoft\MailTracker\Events\EmailSentEvent' => [
        'App\Listeners\EmailSent',
    ],
    'ZanySoft\MailTracker\Events\ViewEmailEvent' => [
        'App\Listeners\EmailViewed',
    ],
    'ZanySoft\MailTracker\Events\LinkClickedEvent' => [
        'App\Listeners\EmailLinkClicked',
    ],
    'ZanySoft\MailTracker\Events\EmailDeliveredEvent' => [
        'App\Listeners\EmailDelivered',
    ],
    'ZanySoft\MailTracker\Events\ComplaintMessageEvent' => [
        'App\Listeners\EmailComplaint',
    ],
    'ZanySoft\MailTracker\Events\PermanentBouncedMessageEvent' => [
        'App\Listeners\BouncedEmail',
    ],
];
```

### Passing data to the event listeners

Often times you may need to link a sent email to another model. The best way to handle this is to add a header to your outgoing email that you can retrieve in your event listener. Here is an example:

```php
/**
 * Send an email and do processing on a model with the email
 */
\Mail::send('email.test', [], function ($message) use($email, $subject, $name, $model) {
    $message->from('from@johndoe.com', 'From Name');
    $message->sender('sender@johndoe.com', 'Sender Name');
    $message->to($email, $name);
    $message->subject($subject);

    // Create a custom header that we can later retrieve
    $message->getHeaders()->addTextHeader("X-MODEL", "Model Name");
    $message->getHeaders()->addTextHeader('X-Model-ID',$model->id);
});
```

and then in your event listener:

```php
public function handle(EmailSentEvent $event)
{
    $tracker = $event->sent_email;
    $model_id = $event->sent_email->getHeader('X-Model-ID');
    // or
    $model_id = $event->sent_email->getModelId();

    $model_name = $event->sent_email->getHeader('X-Model');
    // or
    $model_name = $event->sent_email->getModel();

    $model = app("App\\$model_name")->find($model_id);
    // Perform your tracking/linking tasks on $model knowing the SentEmail object
}
```

Note that the headers you are attaching to the email are actually going out with the message, so do not store any data that you wouldn't want to expose to your email recipients.

## Exceptions

The following exceptions may be thrown. You may add them to your ignore list in your exception handler, or handle them as you wish.

-   ZanySoft\MailTracker\Exceptions\BadUrlLink - Something went wrong with the url link. Either the base 64 encoded url is bad (this only applies to mail sent through version 2.1) or the email hash was not found to apply the link to.

## Amazon SES features

If you use Amazon SES, you can add some additional information to your tracking. To set up the SES callbacks, first set up SES notifications under your domain in the SES control panel. Then subscribe to the topic by going to the admin panel of the notification topic and creating a subscription for the URL you copied from the admin page. The system should immediately respond to the subscription request. If you like, you can use multiple subscriptions (i.e. one for delivery, one for bounces). See above for events that are fired on a failed message. **For added security, it is recommended to set the topic ARN into the mail-tracker config.**

## Views

When you do the php artisan vendor:publish simple views will add to your resources/views/vendor/emailTrakingViews and you can customize.

## Admin Panel

MailTracker comes with a built-in administration area. The default configuration that is published with the package puts it behind the `can:see-sent-emails` middleware; you may create a gate for this rule or change it to use one of your own. You may also change the default prefix as well as disable the admin routes completely.

The route name is 'mailTracker_Index'. The standard admin panel route is located at /email-manager. You can use route names to include them into your existing admin menu. You can customize your route in the config file. You can see all sent emails, total opens, total urls clicks, show individuals emails and show the urls clicked details.

All views (email tamplates, panel) can be customized in resources/views/vendor/emailTrakingViews.

## Security

If you discover any security related issues, please [info@zanysoft.net][email-me] instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/zanysoft/mail-tracker.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/zanysoft/mail-tracker/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/zanysoft/MailTracker.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/zanysoft/MailTracker.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/zanysoft/mail-tracker.svg?style=flat-square
[link-packagist]: https://packagist.org/packages/zanysoft/mail-tracker
[link-travis]: https://travis-ci.org/zanysoft/mail-tracker
[link-downloads]: https://packagist.org/packages/zanysoft/mail-tracker
[link-author]: https://github.com/zanysoft
[email-me]: mailto:info@zanysoft.net
