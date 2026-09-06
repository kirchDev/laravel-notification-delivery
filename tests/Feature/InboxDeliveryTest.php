<?php

declare(strict_types=1);

use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use KirchDev\NotificationDelivery\Channels\InboxChannel;
use KirchDev\NotificationDelivery\Channels\LiveChannel;
use KirchDev\NotificationDelivery\Enums\CoreChannel;
use KirchDev\NotificationDelivery\Events\NotificationBroadcasted;
use KirchDev\NotificationDelivery\Models\DeliveredNotification;
use KirchDev\NotificationDelivery\Tests\Fixtures\Notification\TestNotificationType;
use KirchDev\NotificationDelivery\Tests\Fixtures\User;

it('stores the notification and broadcasts it', function () {
    Event::fake([NotificationBroadcasted::class]);

    $user = makeUser();

    NotificationFacade::send([$user], notificationOf(TestNotificationType::Invited, ['organisation' => 'Acme']));

    $row = DeliveredNotification::query()->sole();

    expect($row->type)->toBe('test.member.invited')
        ->and($row->payload['name'])->toBe('notification.test.member.invited')
        ->and($row->payload['payload'])->toBe(['organisation' => 'Acme'])
        ->and($row->payload['actions'][0]['name'])->toBe('open')
        ->and($row->read_at)->toBeNull()
        ->and($row->getAttribute('notifiable_type'))->toBe($user::class)
        ->and($row->getAttribute('notifiable_id'))->toBe($user->getKey());

    Event::assertDispatched(NotificationBroadcasted::class, function (NotificationBroadcasted $event) use ($row): bool {
        return $event->notification->publicId === $row->publicId()
            && $event->notification->announce === true;
    });
});

it('does not announce when the recipient switched live off', function () {
    Event::fake([NotificationBroadcasted::class]);

    $user = makeUser();
    storePreference($user, TestNotificationType::Invited, CoreChannel::Live, false);

    NotificationFacade::send([$user], notificationOf(TestNotificationType::Invited));

    // The row and the broadcast still happen — only the interruption is switched off, or the
    // bell would show stale numbers until the next reload.
    expect(DeliveredNotification::query()->count())->toBe(1);

    Event::assertDispatched(
        NotificationBroadcasted::class,
        fn (NotificationBroadcasted $event): bool => $event->notification->announce === false,
    );
});

it('stores without broadcasting for a type that opts out of the fan-out', function () {
    Event::fake([NotificationBroadcasted::class]);

    NotificationFacade::send([makeUser()], notificationOf(TestNotificationType::Digest));

    expect(DeliveredNotification::query()->count())->toBe(1);

    Event::assertNotDispatched(NotificationBroadcasted::class);
});

it('names the broadcast channel the way Laravel does', function () {
    $user = makeUser();
    $event = new NotificationBroadcasted($user, notificationOf()->payload($user));

    $channels = $event->broadcastOn();

    expect($channels[0]->name)->toBe('private-'.str_replace('\\', '.', $user::class).'.'.$user->getKey())
        ->and($event->broadcastAs())->toBe('NotificationBroadcasted')
        ->and($event->broadcastWith())->toHaveKeys(['name', 'payload', 'actions', 'publicId', 'announce']);
});

it('stores nothing for a notification that is not typed', function () {
    $user = makeUser();

    $channel = app(InboxChannel::class);

    expect($channel->send($user, new Notification))->toBeNull()
        ->and(DeliveredNotification::query()->count())->toBe(0);
});

it('stores nothing for a notifiable that has no key', function () {
    $channel = app(InboxChannel::class);

    expect($channel->send(new User, notificationOf()))->toBeNull()
        ->and(DeliveredNotification::query()->count())->toBe(0);
});

it('delivers nothing from the live channel itself', function () {
    $user = makeUser();

    // The one WebSocket event is fired by the inbox; `live` only decides `announce` on it.
    expect((new LiveChannel)->send($user, notificationOf()))->toBeNull();
});

it('honours a preference stored after the channel object was built', function () {
    Event::fake([NotificationBroadcasted::class]);

    $user = makeUser();

    // Laravel's ChannelManager is a singleton and caches the channel it built for the first
    // delivery. Anything the channel holds from that first resolve outlives every scope reset —
    // which is what Octane does between requests, and the queue worker between jobs.
    $manager = app(ChannelManager::class);
    $manager->driver(InboxChannel::class)->send($user, notificationOf(TestNotificationType::Invited));

    app()->forgetScopedInstances();

    storePreference($user, TestNotificationType::Invited, CoreChannel::Live, false);

    $manager->driver(InboxChannel::class)->send($user, notificationOf(TestNotificationType::Invited));

    $announced = [];

    Event::assertDispatched(NotificationBroadcasted::class, function ($event) use (&$announced) {
        $announced[] = $event->notification->announce;

        return true;
    });

    expect($announced)->toBe([true, false]);
});
