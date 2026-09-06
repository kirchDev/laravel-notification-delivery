<?php

declare(strict_types=1);

use KirchDev\NotificationDelivery\Enums\CoreChannel;
use KirchDev\NotificationDelivery\Events\NotificationBroadcasted;
use KirchDev\NotificationDelivery\Models\DeliveredNotification;
use KirchDev\NotificationDelivery\Models\NotificationPreference;
use KirchDev\NotificationDelivery\Support\DeliveryDecision;
use KirchDev\NotificationDelivery\Support\DeliveryResolver;
use KirchDev\NotificationDelivery\Tests\Fixtures\BroadcastRoutingUser;
use KirchDev\NotificationDelivery\Tests\Fixtures\Notification\TestNotificationType;

it('lets a recipient name its own broadcast channel', function () {
    $user = BroadcastRoutingUser::create(['name' => 'B', 'email' => 'b@example.com']);
    $event = new NotificationBroadcasted($user, notificationOf()->payload($user));

    expect($event->broadcastOn()[0]->name)->toBe('private-inbox.'.$user->getKey());
});

it('names a channel for a notifiable with no key at all', function () {
    $event = new NotificationBroadcasted(new stdClass, notificationOf()->payload(new stdClass));

    expect($event->broadcastOn()[0]->name)->toBe('private-stdClass.');
});

it('reads the notifiable back off a preference row', function () {
    $user = makeUser();
    storePreference($user, TestNotificationType::Invited, CoreChannel::Mail, false);

    expect(NotificationPreference::query()->sole()->notifiable()->first()?->getKey())->toBe($user->getKey());
});

it('scopes preferences to one recipient', function () {
    $user = makeUser();
    $other = makeUser('other@example.com');

    storePreference($user, TestNotificationType::Invited, CoreChannel::Mail, false);
    storePreference($other, TestNotificationType::Invited, CoreChannel::Live, false);

    expect(NotificationPreference::query()->forNotifiable($user)->pluck('channel')->all())->toBe(['mail']);
});

it('reports a deferred channel and its delay', function () {
    $decision = new DeliveryDecision(deferred: [['channel' => CoreChannel::Mail, 'delaySeconds' => 60]]);

    expect($decision->deferred())->toBe([['channel' => CoreChannel::Mail, 'delaySeconds' => 60]])
        ->and($decision->immediate())->toBe([])
        ->and($decision->laravelChannels())->toBe([])
        ->and($decision->announces())->toBeFalse();
});

it('sends a locked channel straight through the single-channel re-check', function () {
    // Gate 2 stops the chain there, whatever the recipient or the policy would have said.
    expect(app(DeliveryResolver::class)
        ->decideChannel(makeUser(), TestNotificationType::Invited, CoreChannel::Inbox)
        ->isSend())->toBeTrue();
});

it('sends a channel that does not interrupt out of band through the re-check', function () {
    expect(app(DeliveryResolver::class)
        ->decideChannel(makeUser(), TestNotificationType::Invited, CoreChannel::Live)
        ->isSend())->toBeTrue();
});

it('prunes every read notification on a window of zero days', function () {
    $user = makeUser();
    $user->deliveredNotifications()->create(['type' => 'test.read', 'payload' => [], 'read_at' => now()]);
    $user->deliveredNotifications()->create(['type' => 'test.unread', 'payload' => []]);

    $this->artisan('notification-delivery:prune', ['--read-days' => 0])
        ->expectsOutputToContain('Pruned 1 stored notifications.')
        ->assertSuccessful();

    expect(DeliveredNotification::query()->pluck('type')->all())->toBe(['test.unread']);
});
