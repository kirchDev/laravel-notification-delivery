<?php

declare(strict_types=1);

use KirchDev\NotificationDelivery\Actions\ListNotificationPreferences;
use KirchDev\NotificationDelivery\Enums\ChannelPreference;
use KirchDev\NotificationDelivery\Enums\CoreChannel;
use KirchDev\NotificationDelivery\Models\NotificationPreference;
use KirchDev\NotificationDelivery\Support\PreferenceResolver;
use KirchDev\NotificationDelivery\Support\TypeRegistry;
use KirchDev\NotificationDelivery\Tests\Fixtures\Notification\PushOnlyType;
use KirchDev\NotificationDelivery\Tests\Fixtures\Notification\SecurityType;
use KirchDev\NotificationDelivery\Tests\Fixtures\Notification\TestGroup;
use KirchDev\NotificationDelivery\Tests\Fixtures\Notification\TestNotificationType;
use KirchDev\NotificationDelivery\Tests\Fixtures\Notification\UngroupedType;
use KirchDev\NotificationDelivery\Tests\Fixtures\User;

it('resolves the finest tier that has an answer', function () {
    $user = makeUser();
    $resolver = app(PreferenceResolver::class);

    expect($resolver->resolve($user, TestNotificationType::Invited, CoreChannel::Mail))->toBeNull()
        ->and($resolver->source($user, TestNotificationType::Invited, CoreChannel::Mail))->toBe('default');

    storePreference($user, TestGroup::Organisation, CoreChannel::Mail, ChannelPreference::Off);

    expect($resolver->resolve($user, TestNotificationType::Invited, CoreChannel::Mail))->toBeFalse()
        ->and($resolver->source($user, TestNotificationType::Invited, CoreChannel::Mail))->toBe('group');

    storePreference($user, TestNotificationType::Invited, CoreChannel::Mail, ChannelPreference::WhenAway);

    expect($resolver->resolve($user, TestNotificationType::Invited, CoreChannel::Mail))->toBeTrue()
        ->and($resolver->source($user, TestNotificationType::Invited, CoreChannel::Mail))->toBe('type');
});

it('stops at the type tier for an ungrouped type', function () {
    $user = makeUser();
    storePreference($user, TestGroup::Organisation, CoreChannel::Mail, ChannelPreference::Off);

    // UngroupedType::group() is null, so the group row that exists is not its group.
    expect(app(PreferenceResolver::class)->resolve($user, UngroupedType::Plain, CoreChannel::Mail))->toBeNull()
        ->and(app(PreferenceResolver::class)->source($user, UngroupedType::Plain, CoreChannel::Mail))->toBe('default');
});

it('stores only what deviates', function () {
    $user = makeUser();

    expect(NotificationPreference::query()->count())->toBe(0);

    storePreference($user, TestNotificationType::Invited, CoreChannel::Mail, ChannelPreference::Off);

    expect(NotificationPreference::query()->count())->toBe(1);

    // Clearing is not the same as switching off: null puts the recipient back on the type's own
    // default, which is what lets a changed default reach the people who never touched it.
    storePreference($user, TestNotificationType::Invited, CoreChannel::Mail, null);

    expect(NotificationPreference::query()->count())->toBe(0)
        ->and(app(PreferenceResolver::class)->resolve($user, TestNotificationType::Invited, CoreChannel::Mail))->toBeNull();
});

it('updates an existing row rather than adding a second', function () {
    $user = makeUser();

    storePreference($user, TestNotificationType::Invited, CoreChannel::Mail, ChannelPreference::Off);
    storePreference($user, TestNotificationType::Invited, CoreChannel::Mail, ChannelPreference::WhenAway);

    $row = NotificationPreference::query()->sole();

    expect($row->enabled)->toBeTrue()
        ->and($row->type)->toBe('test.member.invited')
        ->and($row->channel)->toBe('mail');
});

it('refuses to store a preference for an unsaved recipient', function () {
    expect(fn () => storePreference(new User(['name' => 'Ghost']), TestNotificationType::Invited, CoreChannel::Mail, ChannelPreference::Off))
        ->toThrow(InvalidArgumentException::class);
});

it('serves repeated lookups from one query', function () {
    $user = makeUser();
    storePreference($user, TestNotificationType::Invited, CoreChannel::Mail, ChannelPreference::Off);

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
    storePreference($user, TestGroup::Organisation, CoreChannel::Mail, ChannelPreference::Off);

    $rows = app(ListNotificationPreferences::class)->execute($user);

    $invitedInbox = collect($rows)->firstWhere(fn (array $row): bool => $row['type'] === 'test.member.invited' && $row['channel'] === 'inbox');
    $invitedMail = collect($rows)->firstWhere(fn (array $row): bool => $row['type'] === 'test.member.invited' && $row['channel'] === 'mail');
    $optInMail = collect($rows)->firstWhere(fn (array $row): bool => $row['type'] === 'test.opt.in' && $row['channel'] === 'mail');

    expect($invitedInbox)->toBe([
        'type' => 'test.member.invited',
        'group' => 'organisation',
        'channel' => 'inbox',
        'preference' => 'on',
        'locked' => true,
        'configurable' => false,
        'quietable' => false,
        'source' => 'default',
    ])
        ->and($invitedMail['preference'])->toBe('off')
        ->and($invitedMail['quietable'])->toBeTrue()
        ->and($invitedMail['source'])->toBe('group')
        ->and($optInMail['preference'])->toBe('off')
        ->and($optInMail['source'])->toBe('group');
});

it('reports what each channel is set to in the settings grid', function () {
    config()->set('notification-delivery.discovery.types', [
        TestNotificationType::class,
        SecurityType::class,
    ]);

    $user = makeUser();
    storePreference($user, TestNotificationType::Invited, CoreChannel::Mail, ChannelPreference::Always);
    storePreference($user, TestNotificationType::Invited, CoreChannel::Live, ChannelPreference::Off);

    $preference = function (string $type, string $channel) use ($user): array {
        $row = collect(app(ListNotificationPreferences::class)->execute($user))
            ->firstWhere(fn (array $row): bool => $row['type'] === $type && $row['channel'] === $channel);

        return [$row['preference'], $row['source']];
    };

    expect($preference('test.member.invited', 'mail'))->toBe(['always', 'type'])
        ->and($preference('test.member.invited', 'live'))->toBe(['off', 'type'])
        ->and($preference('test.digest', 'mail'))->toBe(['when_away', 'default'])
        ->and($preference('test.security.alert', 'mail'))->toBe(['always', 'default'])
        ->and($preference('test.security.alert', 'live'))->toBe(['on', 'default']);

    // A group row outranks the type's own `always` default.
    storePreference($user, TestGroup::Organisation, CoreChannel::Mail, ChannelPreference::WhenAway);

    expect($preference('test.security.alert', 'mail'))->toBe(['when_away', 'group']);
});

it('resolves a stored row as one answer, bypass included', function () {
    $user = makeUser();
    $resolver = app(PreferenceResolver::class);

    storePreference($user, TestGroup::Organisation, CoreChannel::Mail, ChannelPreference::Always);

    expect($resolver->preference($user, TestNotificationType::Invited, CoreChannel::Mail))->toBe(ChannelPreference::Always);

    storePreference($user, TestNotificationType::Invited, CoreChannel::Mail, ChannelPreference::WhenAway);

    expect($resolver->preference($user, TestNotificationType::Invited, CoreChannel::Mail))->toBe(ChannelPreference::WhenAway)
        ->and($resolver->resolve($user, TestNotificationType::Invited, CoreChannel::Mail))->toBeTrue()
        ->and($resolver->preference($user, TestNotificationType::Removed, CoreChannel::Live))->toBeNull();
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

it('stores a quietable channel as off, when away or always', function () {
    $user = makeUser();
    $row = fn (): array => NotificationPreference::query()->sole()->only(['enabled', 'bypass_suppression']);

    storePreference($user, TestNotificationType::Invited, CoreChannel::Mail, ChannelPreference::Always);
    expect($row())->toBe(['enabled' => true, 'bypass_suppression' => true]);

    // Stepping back from "always" to "when away" has to clear the bypass, not leave it behind on
    // the row it updates.
    storePreference($user, TestNotificationType::Invited, CoreChannel::Mail, ChannelPreference::WhenAway);
    expect($row())->toBe(['enabled' => true, 'bypass_suppression' => null]);

    storePreference($user, TestNotificationType::Invited, CoreChannel::Mail, ChannelPreference::Off);
    expect($row())->toBe(['enabled' => false, 'bypass_suppression' => null]);
});

it('stores a channel that is not quietable as off or on', function () {
    $user = makeUser();

    storePreference($user, TestNotificationType::Invited, CoreChannel::Live, ChannelPreference::On);

    expect(NotificationPreference::query()->sole()->only(['enabled', 'bypass_suppression']))
        ->toBe(['enabled' => true, 'bypass_suppression' => null])
        ->and(app(PreferenceResolver::class)->preference($user, TestNotificationType::Invited, CoreChannel::Live))
        ->toBe(ChannelPreference::On);
});

it('refuses a preference the channel cannot express', function (CoreChannel $channel, ChannelPreference $preference) {
    $user = makeUser();

    expect(fn () => storePreference($user, TestNotificationType::Invited, $channel, $preference))
        ->toThrow(InvalidArgumentException::class)
        ->and(NotificationPreference::query()->count())->toBe(0);
})->with([
    'on for a quietable channel' => [CoreChannel::Mail, ChannelPreference::On],
    'when away for a channel that is never quiet' => [CoreChannel::Live, ChannelPreference::WhenAway],
    'always for a channel that is never quiet' => [CoreChannel::Live, ChannelPreference::Always],
]);
