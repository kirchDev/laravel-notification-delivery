<?php

declare(strict_types=1);

use KirchDev\NotificationDelivery\Actions\UpdateNotificationPreference;
use KirchDev\NotificationDelivery\Contracts\Channel;
use KirchDev\NotificationDelivery\Contracts\NotificationGroup;
use KirchDev\NotificationDelivery\Contracts\NotificationType;
use KirchDev\NotificationDelivery\Support\PreferenceResolver;
use KirchDev\NotificationDelivery\Tests\Fixtures\Notification\TestNotificationType;
use KirchDev\NotificationDelivery\Tests\Fixtures\TestNotification;
use KirchDev\NotificationDelivery\Tests\Fixtures\User;
use KirchDev\NotificationDelivery\Tests\TestCase;
use KirchDev\NotificationDelivery\Tests\UlidKeysTestCase;
use KirchDev\NotificationDelivery\Tests\UuidKeysTestCase;

pest()->extend(TestCase::class)->in('Feature');
pest()->extend(UuidKeysTestCase::class)->in('UuidKeys');
pest()->extend(UlidKeysTestCase::class)->in('UlidKeys');

function makeUser(string $email = 'test@example.com', string $name = 'Test'): User
{
    return User::create(['name' => $name, 'email' => $email]);
}

/**
 * @param  array<string, mixed>  $arguments
 */
function notificationOf(?NotificationType $type = null, array $arguments = []): TestNotification
{
    return new TestNotification($type ?? TestNotificationType::Invited, $arguments);
}

/**
 * Store one preference row through the action, so the resolver's cache is invalidated the same
 * way production invalidates it.
 */
function storePreference(
    object $notifiable,
    NotificationType|NotificationGroup $target,
    Channel $channel,
    ?bool $enabled,
): void {
    app(UpdateNotificationPreference::class)
        ->execute($notifiable, $target, $channel, $enabled);
}

function forgetPreferences(object $notifiable): void
{
    app(PreferenceResolver::class)->forget($notifiable);
}
