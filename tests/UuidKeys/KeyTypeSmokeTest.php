<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;
use KirchDev\NotificationDelivery\Actions\UpdateNotificationPreference;
use KirchDev\NotificationDelivery\Enums\CoreChannel;
use KirchDev\NotificationDelivery\Models\DeliveredNotification;
use KirchDev\NotificationDelivery\Models\NotificationPreference;
use KirchDev\NotificationDelivery\Tests\Fixtures\Notification\TestNotificationType;
use KirchDev\NotificationDelivery\Tests\Fixtures\UuidUser;

it('stores and reads a notification on uuid setups', function () {
    $user = UuidUser::create(['name' => 'U', 'email' => 'u@example.com']);

    expect(Str::isUuid($user->getKey()))->toBeTrue();

    NotificationFacade::send([$user], notificationOf(TestNotificationType::Invited));

    $row = DeliveredNotification::query()->sole();

    expect(Str::isUuid($row->getKey()))->toBeTrue()
        ->and($row->getAttribute('notifiable_id'))->toBe($user->getKey())
        ->and($user->unreadNotificationCount())->toBe(1);
});

it('stores a preference on uuid setups', function () {
    $user = UuidUser::create(['name' => 'U', 'email' => 'u2@example.com']);

    app(UpdateNotificationPreference::class)
        ->execute($user, TestNotificationType::Invited, CoreChannel::Mail, false);

    expect(Str::isUuid(NotificationPreference::query()->sole()->getKey()))->toBeTrue();
});
