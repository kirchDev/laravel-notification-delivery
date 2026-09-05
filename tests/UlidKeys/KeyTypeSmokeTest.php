<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;
use KirchDev\NotificationDelivery\Actions\UpdateNotificationPreference;
use KirchDev\NotificationDelivery\Enums\CoreChannel;
use KirchDev\NotificationDelivery\Models\DeliveredNotification;
use KirchDev\NotificationDelivery\Models\NotificationPreference;
use KirchDev\NotificationDelivery\Tests\Fixtures\Notification\TestNotificationType;
use KirchDev\NotificationDelivery\Tests\Fixtures\UlidUser;

it('stores and reads a notification on ulid setups', function () {
    $user = UlidUser::create(['name' => 'U', 'email' => 'u@example.com']);

    expect(Str::isUlid($user->getKey()))->toBeTrue();

    NotificationFacade::send([$user], notificationOf(TestNotificationType::Invited));

    $row = DeliveredNotification::query()->sole();

    expect(Str::isUlid($row->getKey()))->toBeTrue()
        ->and($row->getAttribute('notifiable_id'))->toBe($user->getKey())
        ->and($user->unreadNotificationCount())->toBe(1);
});

it('stores a preference on ulid setups', function () {
    $user = UlidUser::create(['name' => 'U', 'email' => 'u2@example.com']);

    app(UpdateNotificationPreference::class)
        ->execute($user, TestNotificationType::Invited, CoreChannel::Mail, false);

    expect(Str::isUlid(NotificationPreference::query()->sole()->getKey()))->toBeTrue();
});

it('leaves a key that was set by hand alone', function () {
    // The trait generates a key only when there is none. A caller that brought its own — an
    // import, a replayed event — keeps it.
    $user = UlidUser::create(['name' => 'U', 'email' => 'u3@example.com']);

    $row = $user->deliveredNotifications()->create([
        'id' => '01HZZZZZZZZZZZZZZZZZZZZZZZ',
        'type' => 'test.member.removed',
        'payload' => [],
    ]);

    expect($row->getKey())->toBe('01HZZZZZZZZZZZZZZZZZZZZZZZ')
        ->and($row->getKeyType())->toBe('string')
        ->and($row->getIncrementing())->toBeFalse();
});
