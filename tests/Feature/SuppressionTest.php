<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Queue;
use KirchDev\NotificationDelivery\Channels\InboxChannel;
use KirchDev\NotificationDelivery\Channels\LiveChannel;
use KirchDev\NotificationDelivery\Contracts\SuppressionPolicy;
use KirchDev\NotificationDelivery\Enums\CoreChannel;
use KirchDev\NotificationDelivery\Jobs\DeliverDeferredNotification;
use KirchDev\NotificationDelivery\Models\DeliveredNotification;
use KirchDev\NotificationDelivery\Support\DeliveryResolver;
use KirchDev\NotificationDelivery\Support\NeverSuppress;
use KirchDev\NotificationDelivery\Support\SuppressionDecision;
use KirchDev\NotificationDelivery\Tests\Fixtures\DeferMail;
use KirchDev\NotificationDelivery\Tests\Fixtures\DropEverything;
use KirchDev\NotificationDelivery\Tests\Fixtures\Notification\TestNotificationType;
use KirchDev\NotificationDelivery\Tests\Fixtures\TestNotification;
use KirchDev\NotificationDelivery\Tests\Fixtures\User;

it('drops a channel the policy refuses', function () {
    app()->instance(SuppressionPolicy::class, new DropEverything);

    expect(notificationOf(TestNotificationType::Invited)->via(makeUser()))
        ->toBe([InboxChannel::class, LiveChannel::class]);
});

it('never asks the policy about a channel that does not interrupt out of band', function () {
    app()->instance(SuppressionPolicy::class, new DropEverything);

    // `live` is not quietable, so gate 4 is skipped for it — a policy that drops everything
    // still leaves the toast alone.
    expect(notificationOf(TestNotificationType::Invited)->via(makeUser()))->toContain(LiveChannel::class);
});

it('takes a deferred channel out of via() and schedules a job instead', function () {
    Queue::fake();
    app()->instance(SuppressionPolicy::class, new DeferMail);

    $user = makeUser();

    expect(notificationOf(TestNotificationType::Invited)->via($user))
        ->toBe([InboxChannel::class, LiveChannel::class]);

    Queue::assertPushed(
        DeliverDeferredNotification::class,
        fn (DeliverDeferredNotification $job): bool => $job->channel === CoreChannel::Mail
            && $job->delay === 120,
    );
});

it('delivers the held-back channel when the notification is still unread', function () {
    NotificationFacade::fake();

    $user = makeUser();
    $notification = notificationOf(TestNotificationType::Invited);

    (new DeliverDeferredNotification($user, $notification, CoreChannel::Mail))
        ->handle(app(DeliveryResolver::class));

    NotificationFacade::assertSentTo($user, $notification::class);
});

it('discards the held-back channel once the notification has been read', function () {
    NotificationFacade::fake();

    $user = makeUser();
    $notification = notificationOf(TestNotificationType::Invited);

    // Whoever saw the toast has read it by now; whoever was away has not. That is the whole of
    // the escalation logic — "was online" is already inside "if unread".
    $user->deliveredNotifications()->create([
        'type' => 'test.member.invited',
        'payload' => $notification->payload($user)->toStoredArray(),
        'read_at' => now(),
    ]);

    (new DeliverDeferredNotification($user, $notification, CoreChannel::Mail))
        ->handle(app(DeliveryResolver::class));

    NotificationFacade::assertNothingSent();
});

it('delivers when an unread notification of the same type is the newest one', function () {
    NotificationFacade::fake();

    $user = makeUser();
    $notification = notificationOf(TestNotificationType::Invited);

    $user->deliveredNotifications()->create([
        'type' => 'test.member.invited',
        'payload' => [],
        'read_at' => now(),
    ]);
    $user->deliveredNotifications()->create([
        'type' => 'test.member.invited',
        'payload' => [],
    ]);

    (new DeliverDeferredNotification($user, $notification, CoreChannel::Mail))
        ->handle(app(DeliveryResolver::class));

    NotificationFacade::assertSentTo($user, $notification::class);
});

it('discards rather than deferring a second time', function () {
    NotificationFacade::fake();
    app()->instance(SuppressionPolicy::class, new DeferMail);

    $user = makeUser();

    // A policy that always defers would otherwise hold a notification forever, one delay at a
    // time. One hold, then a verdict.
    (new DeliverDeferredNotification($user, notificationOf(), CoreChannel::Mail))
        ->handle(app(DeliveryResolver::class));

    NotificationFacade::assertNothingSent();
});

it('discards a held-back channel the recipient switched off in the meantime', function () {
    NotificationFacade::fake();

    $user = makeUser();
    storePreference($user, TestNotificationType::Invited, CoreChannel::Mail, false);

    (new DeliverDeferredNotification($user, notificationOf(), CoreChannel::Mail))
        ->handle(app(DeliveryResolver::class));

    NotificationFacade::assertNothingSent();
});

it('delivers for a notifiable with no stored rows to check', function () {
    NotificationFacade::fake();

    $user = new User(['name' => 'Ghost', 'email' => 'g@example.com']);

    (new DeliverDeferredNotification($user, notificationOf(), CoreChannel::Mail))
        ->handle(app(DeliveryResolver::class));

    NotificationFacade::assertSentTo($user, TestNotification::class);
});

it('never suppresses by default', function () {
    $decision = (new NeverSuppress)->decide(makeUser(), TestNotificationType::Invited, CoreChannel::Mail);

    expect($decision->isSend())->toBeTrue()
        ->and($decision->isDrop())->toBeFalse()
        ->and($decision->isDefer())->toBeFalse()
        ->and($decision->delaySeconds())->toBe(0);
});

it('treats a delay of zero as a send', function () {
    // Holding something back for no time at all only buys a queue round-trip and a second
    // chance to lose the notification.
    expect(SuppressionDecision::defer(0)->isSend())->toBeTrue()
        ->and(SuppressionDecision::defer(-5)->isSend())->toBeTrue()
        ->and(SuppressionDecision::defer(30)->delaySeconds())->toBe(30);
});

it('writes exactly one inbox row when a deferred mail follows', function () {
    Queue::fake();
    app()->instance(SuppressionPolicy::class, new DeferMail);

    $user = makeUser();
    NotificationFacade::send([$user], notificationOf(TestNotificationType::Invited));

    // The job sends through sendNow() with an explicit channel list rather than re-sending the
    // notification, so via() never runs a second time and no second row appears.
    expect(DeliveredNotification::query()->count())->toBe(1);
});
