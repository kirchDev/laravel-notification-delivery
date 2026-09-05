<?php

declare(strict_types=1);

use KirchDev\NotificationDelivery\Channels\LiveChannel;
use KirchDev\NotificationDelivery\Concerns\HasConfigurableKey;
use KirchDev\NotificationDelivery\Enums\CoreChannel;
use KirchDev\NotificationDelivery\Models\DeliveredNotification;
use KirchDev\NotificationDelivery\Models\NotificationPreference;
use KirchDev\NotificationDelivery\NotificationDelivery;
use KirchDev\NotificationDelivery\Support\ActionData;
use KirchDev\NotificationDelivery\Support\NotificationDefinition;
use KirchDev\NotificationDelivery\Support\PayloadData;
use KirchDev\NotificationDelivery\Tests\Fixtures\Notification\ExtraChannel;
use KirchDev\NotificationDelivery\Tests\Fixtures\Notification\TestGroup;
use KirchDev\NotificationDelivery\Tests\Fixtures\User;

it('separates what is stored from what is broadcast', function () {
    $payload = new PayloadData('n.invited', ['org' => 'Acme'], [new ActionData('open', ['id' => 1], 'action.open')]);

    expect($payload->toStoredArray())->toBe([
        'name' => 'n.invited',
        'payload' => ['org' => 'Acme'],
        'actions' => [['name' => 'open', 'payload' => ['id' => 1], 'label' => 'action.open']],
    ]);

    $delivered = $payload->forDelivery('42', true);

    expect($delivered->toArray()['publicId'])->toBe('42')
        ->and($delivered->toArray()['announce'])->toBeTrue()
        // forDelivery() returns a copy; the original is untouched.
        ->and($payload->publicId)->toBeNull();
});

it('round-trips through an array', function () {
    $payload = new PayloadData('n.invited', ['org' => 'Acme'], [new ActionData('open', ['id' => 1], 'action.open')], '7', true);

    expect(PayloadData::fromArray($payload->toArray())->toArray())->toBe($payload->toArray())
        ->and(json_decode(json_encode($payload) ?: '', true))->toBe($payload->toArray());
});

it('survives a payload that lost its shape', function () {
    $payload = PayloadData::fromArray(['name' => 42, 'payload' => 'nope', 'actions' => ['nope', ['name' => 'ok']]]);

    expect($payload->name)->toBe('')
        ->and($payload->payload)->toBe([])
        ->and($payload->actions)->toHaveCount(1)
        ->and($payload->actions[0]->name)->toBe('ok');
});

it('takes ready-made action objects too', function () {
    $payload = PayloadData::fromArray(['name' => 'n', 'actions' => [new ActionData('open')]]);

    expect($payload->actions[0]->name)->toBe('open');
});

it('survives an action that lost its shape', function () {
    $action = ActionData::fromArray(['payload' => 'nope']);

    expect($action->name)->toBe('')
        ->and($action->label)->toBe('')
        ->and($action->payload)->toBe([])
        ->and(json_decode(json_encode($action) ?: '', true))->toBe($action->toArray());
});

it('derives a definition s channel list from its default and locked lists', function () {
    $definition = new NotificationDefinition(
        default: [CoreChannel::Mail, CoreChannel::Live],
        locked: [CoreChannel::Inbox],
    );

    expect($definition->channels())->toBe([CoreChannel::Inbox, CoreChannel::Live, CoreChannel::Mail])
        ->and($definition->knows(CoreChannel::Mail))->toBeTrue()
        ->and($definition->knows(ExtraChannel::Push))->toBeFalse()
        ->and($definition->isLocked(CoreChannel::Inbox))->toBeTrue()
        ->and($definition->isDefault(CoreChannel::Inbox))->toBeTrue()
        ->and($definition->isDefault(CoreChannel::Mail))->toBeTrue()
        ->and($definition->broadcast)->toBeTrue();
});

it('lists a channel named in both lists once', function () {
    $definition = new NotificationDefinition(
        default: [CoreChannel::Inbox, CoreChannel::Mail],
        locked: [CoreChannel::Inbox],
    );

    expect($definition->channels())->toBe([CoreChannel::Inbox, CoreChannel::Mail]);
});

it('takes an explicit channel list that is wider than the defaults', function () {
    $definition = new NotificationDefinition(
        locked: [CoreChannel::Inbox],
        available: [CoreChannel::Inbox, CoreChannel::Mail],
    );

    expect($definition->knows(CoreChannel::Mail))->toBeTrue()
        ->and($definition->isDefault(CoreChannel::Mail))->toBeFalse();
});

it('describes the core channels', function () {
    expect(CoreChannel::Inbox->userConfigurable())->toBeFalse()
        ->and(CoreChannel::Live->userConfigurable())->toBeTrue()
        ->and(CoreChannel::Inbox->isQuietable())->toBeFalse()
        ->and(CoreChannel::Live->isQuietable())->toBeFalse()
        ->and(CoreChannel::Mail->isQuietable())->toBeTrue()
        ->and(CoreChannel::Live->laravelChannel())->toBe(LiveChannel::class)
        ->and(CoreChannel::Mail->laravelChannel())->toBe('mail')
        ->and(CoreChannel::Inbox->isAvailableFor(new stdClass))->toBeTrue();
});

it('resolves the configured models and columns', function () {
    expect(NotificationDelivery::notificationModel())
        ->toBe(DeliveredNotification::class)
        ->and(NotificationDelivery::preferenceModel())
        ->toBe(NotificationPreference::class)
        ->and(NotificationDelivery::morphKey())->toBe('notifiable_id')
        ->and(NotificationDelivery::keyType())->toBe('id')
        ->and(NotificationDelivery::groupKey(
            TestGroup::Organisation,
        ))->toBe('group:organisation');
});

it('falls back when the config carries nonsense', function () {
    config()->set('notification-delivery.models.notification', '');
    config()->set('notification-delivery.models.preference', 42);
    config()->set('notification-delivery.column_names.notifiable_morph_key', '');
    config()->set('notification-delivery.keys.primary_key_type', 42);
    config()->set('notification-delivery.table_names.notifications', '');
    config()->set('notification-delivery.table_names.preferences', null);

    expect(NotificationDelivery::notificationModel())
        ->toBe(DeliveredNotification::class)
        ->and(NotificationDelivery::preferenceModel())
        ->toBe(NotificationPreference::class)
        ->and(NotificationDelivery::morphKey())->toBe('notifiable_id')
        ->and(NotificationDelivery::keyType())->toBe('id')
        ->and((new DeliveredNotification)->getTable())->toBe('delivered_notifications')
        ->and((new NotificationPreference)->getTable())->toBe('notification_preferences')
        ->and(HasConfigurableKey::class)->toBeString();
});

it('identifies a notifiable that has no key by the object itself', function () {
    $one = new User;
    $two = new User;

    expect(NotificationDelivery::identityFor($one))
        ->not->toBe(NotificationDelivery::identityFor($two))
        ->and(NotificationDelivery::morphKeyFor(new stdClass))->toBeNull()
        ->and(NotificationDelivery::morphTypeFor(new stdClass))->toBe(stdClass::class);
});
