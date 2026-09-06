<?php

declare(strict_types=1);

use KirchDev\NotificationDelivery\Enums\CoreChannel;
use KirchDev\NotificationDelivery\Support\ChannelRegistry;
use KirchDev\NotificationDelivery\Support\TypeRegistry;
use KirchDev\NotificationDelivery\Tests\Fixtures\Notification\ExtraChannel;
use KirchDev\NotificationDelivery\Tests\Fixtures\Notification\TestNotificationType;

it('lists the core channels in UI order', function () {
    expect((new ChannelRegistry)->all())->toBe([CoreChannel::Inbox, CoreChannel::Live, CoreChannel::Mail]);
});

it('takes an application s own channel enum alongside the core one', function () {
    config()->set('notification-delivery.channels.enums', [ExtraChannel::class, CoreChannel::class]);

    $registry = new ChannelRegistry;

    expect($registry->all())->toBe([CoreChannel::Inbox, CoreChannel::Live, CoreChannel::Mail, ExtraChannel::Push])
        ->and($registry->find('push'))->toBe(ExtraChannel::Push)
        ->and($registry->find('carrier-pigeon'))->toBeNull();
});

it('ignores anything registered that is not a channel', function () {
    config()->set('notification-delivery.channels.enums', ['App\\Nope', 42, TestNotificationType::class]);

    expect((new ChannelRegistry)->all())->toBe([]);
});

it('ignores a channels block that is not a list at all', function () {
    config()->set('notification-delivery.channels.enums', 'CoreChannel');

    expect((new ChannelRegistry)->all())->toBe([]);
});

it('lists the registered types', function () {
    $registry = new TypeRegistry;

    expect($registry->all())->toBe(TestNotificationType::cases())
        ->and($registry->find('test.member.invited'))->toBe(TestNotificationType::Invited)
        ->and($registry->find('nope'))->toBeNull()
        // Memoised: a settings page asks it once per type and channel.
        ->and($registry->all())->toBe($registry->all());
});

it('ignores anything registered that is not a type', function () {
    config()->set('notification-delivery.discovery.types', [CoreChannel::class, null]);

    expect((new TypeRegistry)->all())->toBe([]);
});

it('ignores a discovery block that is not a list at all', function () {
    config()->set('notification-delivery.discovery.types', 7);

    expect((new TypeRegistry)->all())->toBe([]);
});
