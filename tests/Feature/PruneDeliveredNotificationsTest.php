<?php

declare(strict_types=1);

use KirchDev\NotificationDelivery\Models\DeliveredNotification;

function storeNotification(object $user, string $type, int $ageInDays, ?int $readAgeInDays = null): DeliveredNotification
{
    /** @var DeliveredNotification $row */
    $row = $user->deliveredNotifications()->create([
        'type' => $type,
        'payload' => ['name' => $type],
        'read_at' => $readAgeInDays === null ? null : now()->subDays($readAgeInDays),
    ]);

    $row->forceFill(['created_at' => now()->subDays($ageInDays)])->save();

    return $row;
}

it('prunes nothing while no retention window is configured', function () {
    $user = makeUser();
    storeNotification($user, 'test.old', 3650);

    $this->artisan('notification-delivery:prune')
        ->expectsOutputToContain('No retention window configured')
        ->assertSuccessful();

    // Never delete is the default on purpose: a record of who was invited when can be the reason
    // somebody still has access years later.
    expect(DeliveredNotification::query()->count())->toBe(1);
});

it('prunes past the shared retention window', function () {
    config()->set('notification-delivery.prune.retention_days', 30);

    $user = makeUser();
    storeNotification($user, 'test.old', 60);
    storeNotification($user, 'test.recent', 5);

    $this->artisan('notification-delivery:prune')
        ->expectsOutputToContain('Pruned 1 stored notifications.')
        ->assertSuccessful();

    expect(DeliveredNotification::query()->pluck('type')->all())->toBe(['test.recent']);
});

it('prunes read notifications on their own shorter window', function () {
    config()->set('notification-delivery.prune.retention_days_read', 7);

    $user = makeUser();
    storeNotification($user, 'test.read', 20, readAgeInDays: 10);
    storeNotification($user, 'test.unread', 20);

    $this->artisan('notification-delivery:prune')->assertSuccessful();

    expect(DeliveredNotification::query()->pluck('type')->all())->toBe(['test.unread']);
});

it('takes both windows from the command line', function () {
    $user = makeUser();
    storeNotification($user, 'test.read', 5, readAgeInDays: 4);
    storeNotification($user, 'test.old', 40);
    storeNotification($user, 'test.recent', 1);

    $this->artisan('notification-delivery:prune', ['--days' => 30, '--read-days' => 3])
        ->expectsOutputToContain('Pruned 2 stored notifications.')
        ->assertSuccessful();

    expect(DeliveredNotification::query()->pluck('type')->all())->toBe(['test.recent']);
});

it('refuses a negative window rather than clamping it to now', function () {
    $user = makeUser();
    storeNotification($user, 'test.recent', 1);

    // Clamping would read as "delete everything older than right now" — the one outcome a typo
    // in a retention setting must never produce.
    $this->artisan('notification-delivery:prune', ['--days' => -5])
        ->expectsOutputToContain('must not be negative')
        ->assertFailed();

    expect(DeliveredNotification::query()->count())->toBe(1);
});

it('prunes in chunks rather than one statement over months of rows', function () {
    config()->set('notification-delivery.prune.retention_days', 1);

    $user = makeUser();

    foreach (range(1, 5) as $offset) {
        storeNotification($user, 'test.old.'.$offset, 10);
    }

    $this->artisan('notification-delivery:prune')
        ->expectsOutputToContain('Pruned 5 stored notifications.')
        ->assertSuccessful();

    expect(DeliveredNotification::query()->count())->toBe(0);
});
