<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use KirchDev\NotificationDelivery\Enums\CoreChannel;
use KirchDev\NotificationDelivery\Support\DeviceSessionSuppression;
use KirchDev\NotificationDelivery\Tests\Fixtures\Notification\TestNotificationType;
use KirchDev\NotificationDelivery\Tests\Fixtures\User;

beforeEach(function () {
    // The optional dependency's own table, as device-sessions would create it. Only the columns
    // this policy reads are needed.
    Schema::create('user_devices', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->timestamp('last_seen_at')->nullable();
        $table->timestamp('revoked_at')->nullable();
        $table->timestamps();
    });
});

function registerDevice(object $user, ?int $secondsAgo, bool $revoked = false): void
{
    DB::table('user_devices')->insert([
        'user_id' => $user->getKey(),
        'last_seen_at' => $secondsAgo === null ? null : now()->subSeconds($secondsAgo),
        'revoked_at' => $revoked ? now() : null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('holds a quietable channel back for a recipient who is present', function () {
    $user = makeUser();
    registerDevice($user, 30);

    $decision = (new DeviceSessionSuppression)->decide($user, TestNotificationType::Invited, CoreChannel::Mail);

    expect($decision->isDefer())->toBeTrue()
        ->and($decision->delaySeconds())->toBe(120);
});

it('sends straight away for a recipient nobody has seen', function () {
    $user = makeUser();
    registerDevice($user, 3600);

    expect((new DeviceSessionSuppression)->decide($user, TestNotificationType::Invited, CoreChannel::Mail)->isSend())
        ->toBeTrue();
});

it('ignores a revoked device', function () {
    $user = makeUser();
    registerDevice($user, 10, revoked: true);

    expect((new DeviceSessionSuppression)->decide($user, TestNotificationType::Invited, CoreChannel::Mail)->isSend())
        ->toBeTrue();
});

it('never holds back a channel that does not interrupt out of band', function () {
    $user = makeUser();
    registerDevice($user, 10);

    // `live` is the toast in the tab they are looking at. Holding it back for somebody who is
    // present is the opposite of what presence means.
    expect((new DeviceSessionSuppression)->decide($user, TestNotificationType::Invited, CoreChannel::Live)->isSend())
        ->toBeTrue();
});

it('sends for a notifiable that is not a model', function () {
    expect((new DeviceSessionSuppression)->decide(new stdClass, TestNotificationType::Invited, CoreChannel::Mail)->isSend())
        ->toBeTrue();
});

it('takes both windows from config', function () {
    config()->set('notification-delivery.suppression.presence_window', 10);
    config()->set('notification-delivery.suppression.defer', 45);

    $user = makeUser();
    registerDevice($user, 30);

    expect((new DeviceSessionSuppression)->decide($user, TestNotificationType::Invited, CoreChannel::Mail)->isSend())
        ->toBeTrue();

    registerDevice($user, 5);

    expect((new DeviceSessionSuppression)->decide($user, TestNotificationType::Invited, CoreChannel::Mail)->delaySeconds())
        ->toBe(45);
});

it('falls back to its own windows when the config carries nonsense', function () {
    config()->set('notification-delivery.suppression.defer', 'soon');

    $user = makeUser();
    registerDevice($user, 30);

    expect((new DeviceSessionSuppression)->decide($user, TestNotificationType::Invited, CoreChannel::Mail)->delaySeconds())
        ->toBe(120);
});

it('reads user_devices, never a column the application owns', function () {
    // gildstone writes users.last_seen_at from a hand-built listener; another project has no such
    // column at all. A policy that depended on it would silently never suppress there.
    $user = makeUser();
    registerDevice($user, 30);

    expect(Schema::hasColumn('users', 'last_seen_at'))->toBeFalse()
        ->and((new DeviceSessionSuppression)->decide($user, TestNotificationType::Invited, CoreChannel::Mail)->isDefer())
        ->toBeTrue()
        ->and(User::query()->count())->toBe(1);
});
