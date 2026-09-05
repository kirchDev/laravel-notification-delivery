<?php

declare(strict_types=1);

use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use KirchDev\NotificationDelivery\Enums\CoreChannel;
use KirchDev\NotificationDelivery\Models\DeliveredNotification;
use KirchDev\NotificationDelivery\Tests\Fixtures\Notification\TestNotificationType;
use KirchDev\NotificationDelivery\Tests\Fixtures\User;

it('exposes the recipient s own inbox, newest first', function () {
    $user = makeUser();
    $other = makeUser('other@example.com');

    NotificationFacade::send([$user], notificationOf(TestNotificationType::Removed));
    NotificationFacade::send([$user], notificationOf(TestNotificationType::Invited));
    NotificationFacade::send([$other], notificationOf(TestNotificationType::Invited));

    expect($user->deliveredNotifications()->pluck('type')->all())
        ->toBe(['test.member.invited', 'test.member.removed']);
});

it('exposes the unread ones and their count', function () {
    $user = makeUser();

    NotificationFacade::send([$user], notificationOf(TestNotificationType::Removed));
    NotificationFacade::send([$user], notificationOf(TestNotificationType::Invited));

    DeliveredNotification::query()->firstOrFail()->markAsRead();

    expect($user->unreadNotifications()->count())->toBe(1)
        ->and($user->unreadNotificationCount())->toBe(1);
});

it('exposes the recipient s preferences', function () {
    $user = makeUser();
    storePreference($user, TestNotificationType::Invited, CoreChannel::Mail, false);

    expect($user->notificationPreferences()->pluck('channel')->all())->toBe(['mail']);
});

it('reads the notifiable back off a stored row', function () {
    $user = makeUser();
    NotificationFacade::send([$user], notificationOf(TestNotificationType::Removed));

    expect(DeliveredNotification::query()->sole()->notifiable()->first()?->getKey())->toBe($user->getKey());
});

it('would collide with Laravel s own Notifiable', function () {
    // The whole reason the guideline says to replace Notifiable rather than add to it: Notifiable
    // is RoutesNotifications *plus* HasDatabaseNotifications, and the latter's notifications()
    // relation points at Laravel's own table, which this package never writes.
    expect(class_uses_recursive(User::class))->not->toContain(Notifiable::class);
});
