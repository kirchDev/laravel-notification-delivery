<?php

declare(strict_types=1);

use KirchDev\NotificationDelivery\Actions\ListNotificationPreferences;
use KirchDev\NotificationDelivery\Enums\CoreChannel;
use KirchDev\NotificationDelivery\Models\NotificationPreference;
use KirchDev\NotificationDelivery\Support\PreferenceResolver;
use KirchDev\NotificationDelivery\Support\TypeRegistry;
use KirchDev\NotificationDelivery\Tests\Fixtures\Notification\PushOnlyType;
use KirchDev\NotificationDelivery\Tests\Fixtures\Notification\TestGroup;
use KirchDev\NotificationDelivery\Tests\Fixtures\Notification\TestNotificationType;
use KirchDev\NotificationDelivery\Tests\Fixtures\Notification\UngroupedType;
use KirchDev\NotificationDelivery\Tests\Fixtures\User;

it('resolves the finest tier that has an answer', function () {
    $user = makeUser();
    $resolver = app(PreferenceResolver::class);

    expect($resolver->resolve($user, TestNotificationType::Invited, CoreChannel::Mail))->toBeNull()
        ->and($resolver->source($user, TestNotificationType::Invited, CoreChannel::Mail))->toBe('default');

    storePreference($user, TestGroup::Organisation, CoreChannel::Mail, false);

    expect($resolver->resolve($user, TestNotificationType::Invited, CoreChannel::Mail))->toBeFalse()
        ->and($resolver->source($user, TestNotificationType::Invited, CoreChannel::Mail))->toBe('group');

    storePreference($user, TestNotificationType::Invited, CoreChannel::Mail, true);

    expect($resolver->resolve($user, TestNotificationType::Invited, CoreChannel::Mail))->toBeTrue()
        ->and($resolver->source($user, TestNotificationType::Invited, CoreChannel::Mail))->toBe('type');
});

it('stops at the type tier for an ungrouped type', function () {
    $user = makeUser();
    storePreference($user, TestGroup::Organisation, CoreChannel::Mail, false);

    // UngroupedType::group() is null, so the group row that exists is not its group.
    expect(app(PreferenceResolver::class)->resolve($user, UngroupedType::Plain, CoreChannel::Mail))->toBeNull()
        ->and(app(PreferenceResolver::class)->source($user, UngroupedType::Plain, CoreChannel::Mail))->toBe('default');
});

it('stores only what deviates', function () {
    $user = makeUser();

    expect(NotificationPreference::query()->count())->toBe(0);

    storePreference($user, TestNotificationType::Invited, CoreChannel::Mail, false);

    expect(NotificationPreference::query()->count())->toBe(1);

    // Clearing is not the same as switching off: null puts the recipient back on the type's own
    // default, which is what lets a changed default reach the people who never touched it.
    storePreference($user, TestNotificationType::Invited, CoreChannel::Mail, null);

    expect(NotificationPreference::query()->count())->toBe(0)
        ->and(app(PreferenceResolver::class)->resolve($user, TestNotificationType::Invited, CoreChannel::Mail))->toBeNull();
});

it('updates an existing row rather than adding a second', function () {
    $user = makeUser();

    storePreference($user, TestNotificationType::Invited, CoreChannel::Mail, false);
    storePreference($user, TestNotificationType::Invited, CoreChannel::Mail, true);

    $row = NotificationPreference::query()->sole();

    expect($row->enabled)->toBeTrue()
        ->and($row->type)->toBe('test.member.invited')
        ->and($row->channel)->toBe('mail');
});

it('refuses to store a preference for an unsaved recipient', function () {
    expect(fn () => storePreference(new User(['name' => 'Ghost']), TestNotificationType::Invited, CoreChannel::Mail, false))
        ->toThrow(InvalidArgumentException::class);
});

it('serves repeated lookups from one query', function () {
    $user = makeUser();
    storePreference($user, TestNotificationType::Invited, CoreChannel::Mail, false);

    $resolver = app(PreferenceResolver::class);
    $resolver->resolve($user, TestNotificationType::Invited, CoreChannel::Mail);

    DB::enableQueryLog();
    $resolver->resolve($user, TestNotificationType::Invited, CoreChannel::Mail);
    $resolver->resolve($user, TestNotificationType::Removed, CoreChannel::Live);

    expect(DB::getQueryLog())->toBeEmpty();

    $resolver->flush();
    $resolver->resolve($user, TestNotificationType::Invited, CoreChannel::Mail);

    expect(DB::getQueryLog())->toHaveCount(1);

    DB::disableQueryLog();
});

it('renders the settings grid', function () {
    $user = makeUser();
    storePreference($user, TestGroup::Organisation, CoreChannel::Mail, false);

    $rows = app(ListNotificationPreferences::class)->execute($user);

    $invitedInbox = collect($rows)->firstWhere(fn (array $row): bool => $row['type'] === 'test.member.invited' && $row['channel'] === 'inbox');
    $invitedMail = collect($rows)->firstWhere(fn (array $row): bool => $row['type'] === 'test.member.invited' && $row['channel'] === 'mail');
    $optInMail = collect($rows)->firstWhere(fn (array $row): bool => $row['type'] === 'test.opt.in' && $row['channel'] === 'mail');

    expect($invitedInbox)->toBe([
        'type' => 'test.member.invited',
        'group' => 'organisation',
        'channel' => 'inbox',
        'enabled' => true,
        'locked' => true,
        'configurable' => false,
        'source' => 'default',
    ])
        ->and($invitedMail['enabled'])->toBeFalse()
        ->and($invitedMail['source'])->toBe('group')
        ->and($optInMail['enabled'])->toBeFalse()
        ->and($optInMail['source'])->toBe('group');
});

it('leaves out a channel the recipient cannot receive on', function () {
    config()->set('notification-delivery.discovery.types', [
        PushOnlyType::class,
    ]);

    $withoutName = makeUser(name: '');
    $withName = makeUser('named@example.com', 'Named');

    $channels = fn (object $user): array => array_column(
        (new ListNotificationPreferences(
            new TypeRegistry,
            app(PreferenceResolver::class),
        ))->execute($user),
        'channel',
    );

    expect($channels($withoutName))->toBe(['inbox'])
        ->and($channels($withName))->toBe(['inbox', 'push']);
});
