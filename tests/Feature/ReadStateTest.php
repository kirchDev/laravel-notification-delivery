<?php

declare(strict_types=1);

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use KirchDev\NotificationDelivery\Actions\CountUnreadNotifications;
use KirchDev\NotificationDelivery\Actions\ListNotifications;
use KirchDev\NotificationDelivery\Actions\MarkAllNotificationsAsRead;
use KirchDev\NotificationDelivery\Actions\MarkNotificationAsRead;
use KirchDev\NotificationDelivery\Events\NotificationRead;
use KirchDev\NotificationDelivery\Models\DeliveredNotification;
use KirchDev\NotificationDelivery\Tests\Fixtures\Notification\TestNotificationType;
use KirchDev\NotificationDelivery\Tests\Fixtures\User;

beforeEach(function () {
    $this->user = makeUser();
    $this->other = makeUser('other@example.com');
});

it('lists a recipient s own notifications, newest first', function () {
    NotificationFacade::send([$this->user], notificationOf(TestNotificationType::Removed));
    NotificationFacade::send([$this->user], notificationOf(TestNotificationType::Invited));
    NotificationFacade::send([$this->other], notificationOf(TestNotificationType::Invited));

    $listed = app(ListNotifications::class)->execute($this->user);

    expect($listed)->toHaveCount(2)
        ->and($listed->first()?->type)->toBe('test.member.invited');
});

it('lists only the unread ones when asked', function () {
    NotificationFacade::send([$this->user], notificationOf(TestNotificationType::Removed));
    NotificationFacade::send([$this->user], notificationOf(TestNotificationType::Invited));

    DeliveredNotification::query()->firstOrFail()->markAsRead();

    expect(app(ListNotifications::class)->execute($this->user, unreadOnly: true))->toHaveCount(1);
});

it('honours the limit', function () {
    foreach (range(1, 3) as $ignored) {
        NotificationFacade::send([$this->user], notificationOf(TestNotificationType::Removed));
    }

    expect(app(ListNotifications::class)->execute($this->user, limit: 2))->toHaveCount(2)
        ->and(app(ListNotifications::class)->execute($this->user, limit: 0))->toHaveCount(1);
});

it('counts the unread ones', function () {
    NotificationFacade::send([$this->user], notificationOf(TestNotificationType::Removed));
    NotificationFacade::send([$this->user], notificationOf(TestNotificationType::Invited));
    NotificationFacade::send([$this->other], notificationOf(TestNotificationType::Invited));

    expect(app(CountUnreadNotifications::class)->execute($this->user))->toBe(2)
        ->and($this->user->unreadNotificationCount())->toBe(2);
});

it('marks one notification read', function () {
    NotificationFacade::send([$this->user], notificationOf(TestNotificationType::Removed));

    $row = DeliveredNotification::query()->sole();

    expect(app(MarkNotificationAsRead::class)->execute($this->user, $row->getKey()))->toBeTrue()
        ->and($row->fresh()?->isRead())->toBeTrue();
});

it('does not move the timestamp on a second read', function () {
    NotificationFacade::send([$this->user], notificationOf(TestNotificationType::Removed));

    $row = DeliveredNotification::query()->sole();
    $row->markAsRead();
    $readAt = $row->fresh()?->read_at;

    expect(app(MarkNotificationAsRead::class)->execute($this->user, $row->getKey()))->toBeFalse()
        ->and($row->fresh()?->read_at?->toIso8601String())->toBe($readAt?->toIso8601String());
});

it('cannot reach another recipient s row', function () {
    NotificationFacade::send([$this->other], notificationOf(TestNotificationType::Removed));

    $row = DeliveredNotification::query()->sole();

    expect(app(MarkNotificationAsRead::class)->execute($this->user, $row->getKey()))->toBeFalse()
        ->and($row->fresh()?->isRead())->toBeFalse();
});

it('marks everything read at once', function () {
    NotificationFacade::send([$this->user], notificationOf(TestNotificationType::Removed));
    NotificationFacade::send([$this->user], notificationOf(TestNotificationType::Invited));
    NotificationFacade::send([$this->other], notificationOf(TestNotificationType::Invited));

    expect(app(MarkAllNotificationsAsRead::class)->execute($this->user))->toBe(2)
        ->and(app(CountUnreadNotifications::class)->execute($this->user))->toBe(0)
        ->and(app(CountUnreadNotifications::class)->execute($this->other))->toBe(1);
});

it('answers for an unsaved recipient without touching the database', function () {
    $ghost = new User(['name' => 'Ghost']);

    expect(app(ListNotifications::class)->execute($ghost))->toBeEmpty()
        ->and(app(CountUnreadNotifications::class)->execute($ghost))->toBe(0)
        ->and(app(MarkAllNotificationsAsRead::class)->execute($ghost))->toBe(0)
        ->and(app(MarkNotificationAsRead::class)->execute($ghost, 1))->toBeFalse();
});

it('announces a single read with the row s public id and the new unread count', function () {
    NotificationFacade::send([$this->user], notificationOf(TestNotificationType::Removed));
    NotificationFacade::send([$this->user], notificationOf(TestNotificationType::Invited));
    Event::fake([NotificationRead::class]);

    $row = DeliveredNotification::query()->firstOrFail();
    app(MarkNotificationAsRead::class)->execute($this->user, $row->getKey());

    Event::assertDispatchedTimes(NotificationRead::class, 1);
    Event::assertDispatched(NotificationRead::class, fn (NotificationRead $event): bool => $event->notifiable->is($this->user)
        && $event->publicId === $row->publicId()
        && $event->unreadCount === 1);
});

it('announces mark-all with no public id and an unread count of zero', function () {
    NotificationFacade::send([$this->user], notificationOf(TestNotificationType::Removed));
    NotificationFacade::send([$this->user], notificationOf(TestNotificationType::Invited));
    Event::fake([NotificationRead::class]);

    app(MarkAllNotificationsAsRead::class)->execute($this->user);

    Event::assertDispatchedTimes(NotificationRead::class, 1);
    Event::assertDispatched(NotificationRead::class, fn (NotificationRead $event): bool => $event->notifiable->is($this->user)
        && $event->publicId === null
        && $event->unreadCount === 0);
});

it('announces nothing when no row moved', function () {
    NotificationFacade::send([$this->user], notificationOf(TestNotificationType::Removed));
    NotificationFacade::send([$this->other], notificationOf(TestNotificationType::Removed));
    $own = DeliveredNotification::query()->forNotifiable($this->user)->sole();
    $foreign = DeliveredNotification::query()->forNotifiable($this->other)->sole();
    $own->markAsRead();
    Event::fake([NotificationRead::class]);

    app(MarkNotificationAsRead::class)->execute($this->user, $own->getKey());      // already read
    app(MarkNotificationAsRead::class)->execute($this->user, $foreign->getKey());  // not theirs
    app(MarkNotificationAsRead::class)->execute($this->user, 999999);              // unknown
    app(MarkAllNotificationsAsRead::class)->execute($this->user);                  // nothing unread
    app(MarkAllNotificationsAsRead::class)->execute(new User(['name' => 'Ghost']));

    Event::assertNotDispatched(NotificationRead::class);
});

it('announces a read even for a type that does not broadcast its delivery', function () {
    $this->user->deliveredNotifications()->create(['type' => 'test.bulk', 'payload' => []]);
    Event::fake([NotificationRead::class]);

    app(MarkAllNotificationsAsRead::class)->execute($this->user);

    Event::assertDispatched(NotificationRead::class);
});

it('keeps the model s own markAsRead silent', function () {
    NotificationFacade::send([$this->user], notificationOf(TestNotificationType::Removed));
    Event::fake([NotificationRead::class]);

    DeliveredNotification::query()->sole()->markAsRead();

    Event::assertNotDispatched(NotificationRead::class);
});

it('broadcasts a read on the recipient s private channel under its own name', function () {
    $event = new NotificationRead($this->user, '42', 3);

    expect($event)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($event)->not->toBeInstanceOf(ShouldBroadcastNow::class)
        ->and($event->broadcastOn()[0]->name)->toBe('private-'.str_replace('\\', '.', User::class).'.'.$this->user->getKey())
        ->and($event->broadcastAs())->toBe('NotificationRead')
        ->and($event->broadcastWith())->toBe(['publicId' => '42', 'unreadCount' => 3]);
});

it('reads the stored payload back as the object that wrote it', function () {
    NotificationFacade::send([$this->user], notificationOf(TestNotificationType::Invited, ['organisation' => 'Acme']));

    $payload = DeliveredNotification::query()->sole()->payloadData();

    expect($payload->name)->toBe('notification.test.member.invited')
        ->and($payload->payload)->toBe(['organisation' => 'Acme'])
        ->and($payload->actions[0]->label)->toBe('action.open')
        // Neither field is stored: the public id is the row's own key, and `announce` describes
        // a moment that has passed by the time anyone reads the row back.
        ->and($payload->publicId)->toBeNull()
        ->and($payload->announce)->toBeFalse();
});
