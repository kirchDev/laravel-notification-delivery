<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification as NotificationFacade;
use KirchDev\NotificationDelivery\Enums\CoreChannel;
use KirchDev\NotificationDelivery\Models\DeliveredNotification;
use KirchDev\NotificationDelivery\Models\NotificationPreference;
use KirchDev\NotificationDelivery\Tests\Fixtures\Notification\TestNotificationType;

/**
 * The morph key column is configurable, and the migrations read that config at run time. What is
 * easy to miss is the other half: fill() intersects the incoming attributes with getFillable()
 * before isFillable() is ever consulted, so a renamed column has to be in the list itself — or
 * every write lands with a null recipient.
 */
beforeEach(function () {
    config()->set('notification-delivery.column_names.notifiable_morph_key', 'recipient_id');

    $this->restoreBaselineSchema();
});

it('stores a notification under a renamed morph key column', function () {
    $user = makeUser();

    NotificationFacade::send([$user], notificationOf(TestNotificationType::Invited));

    $row = DeliveredNotification::query()->sole();

    expect($row->getAttribute('recipient_id'))->toEqual($user->getKey())
        ->and($user->deliveredNotifications()->count())->toBe(1);
});

it('stores a preference under a renamed morph key column', function () {
    $user = makeUser();

    storePreference($user, TestNotificationType::Invited, CoreChannel::Mail, false);

    expect(NotificationPreference::query()->sole()->getAttribute('recipient_id'))->toEqual($user->getKey())
        ->and(notificationOf(TestNotificationType::Invited)->via($user))->not->toContain('mail');
});
