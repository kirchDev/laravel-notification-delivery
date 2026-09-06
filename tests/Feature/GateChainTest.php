<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification as NotificationFacade;
use KirchDev\NotificationDelivery\Channels\InboxChannel;
use KirchDev\NotificationDelivery\Channels\LiveChannel;
use KirchDev\NotificationDelivery\Enums\CoreChannel;
use KirchDev\NotificationDelivery\Support\DeliveryResolver;
use KirchDev\NotificationDelivery\Tests\Fixtures\Notification\ExtraChannel;
use KirchDev\NotificationDelivery\Tests\Fixtures\Notification\PushOnlyType;
use KirchDev\NotificationDelivery\Tests\Fixtures\Notification\TestGroup;
use KirchDev\NotificationDelivery\Tests\Fixtures\Notification\TestNotificationType;
use KirchDev\NotificationDelivery\Tests\Fixtures\User;

it('sends every default channel when the recipient has said nothing', function () {
    $user = makeUser();

    expect(notificationOf(TestNotificationType::Invited)->via($user))
        ->toBe([InboxChannel::class, LiveChannel::class, 'mail']);
});

it('keeps a locked channel whatever the recipient asks for', function () {
    $user = makeUser();

    // Gate 2 stops the chain before the preference is even read, which is why the write below
    // has to go through the model: the action refuses a channel that is not user-configurable.
    $user->notificationPreferences()->create([
        'type' => 'test.member.invited',
        'channel' => 'inbox',
        'enabled' => false,
    ]);
    forgetPreferences($user);

    expect(notificationOf(TestNotificationType::Invited)->via($user))->toContain(InboxChannel::class);
});

it('refuses to store a preference for a channel that is not user-configurable', function () {
    $user = makeUser();

    expect(fn () => storePreference($user, TestNotificationType::Invited, CoreChannel::Inbox, false))
        ->toThrow(InvalidArgumentException::class);
});

it('drops a channel the recipient switched off', function () {
    $user = makeUser();
    storePreference($user, TestNotificationType::Invited, CoreChannel::Mail, false);

    expect(notificationOf(TestNotificationType::Invited)->via($user))
        ->toBe([InboxChannel::class, LiveChannel::class]);
});

it('keeps a channel the recipient switched on that is off by default', function () {
    $user = makeUser();

    expect(notificationOf(TestNotificationType::OptIn)->via($user))->toBe([InboxChannel::class]);

    storePreference($user, TestNotificationType::OptIn, CoreChannel::Mail, true);

    expect(notificationOf(TestNotificationType::OptIn)->via($user))->toBe([InboxChannel::class, 'mail']);
});

it('drops a channel this recipient cannot receive on', function () {
    $user = makeUser(name: '');

    // ExtraChannel::Push is available only to a recipient with a name, standing in for "has a
    // device registered". Gate 1 turns it down before any preference is consulted.
    expect(notificationOf(PushOnlyType::Alert)->via($user))->toBe([InboxChannel::class]);

    $withName = makeUser('other@example.com', 'Named');

    expect(notificationOf(PushOnlyType::Alert)->via($withName))->toBe([InboxChannel::class, 'push']);
});

it('drops mail for a recipient with no mail route', function () {
    $user = makeUser(email: '');

    expect(notificationOf(TestNotificationType::Invited)->via($user))
        ->toBe([InboxChannel::class, LiveChannel::class]);
});

it('drops mail for a notifiable that does not route notifications at all', function () {
    expect(CoreChannel::Mail->isAvailableFor(new stdClass))->toBeFalse();
});

it('lets a group preference stand in for every type in it', function () {
    $user = makeUser();
    storePreference($user, TestGroup::Organisation, CoreChannel::Mail, false);

    expect(notificationOf(TestNotificationType::Invited)->via($user))
        ->toBe([InboxChannel::class, LiveChannel::class]);
});

it('lets a type preference win over the group it belongs to', function () {
    $user = makeUser();
    storePreference($user, TestGroup::Organisation, CoreChannel::Mail, false);
    storePreference($user, TestNotificationType::Invited, CoreChannel::Mail, true);

    expect(notificationOf(TestNotificationType::Invited)->via($user))->toContain('mail');
});

it('answers the same question the same way twice', function () {
    $user = makeUser();
    $notification = notificationOf();
    $resolver = app(DeliveryResolver::class);

    expect($resolver->decide($user, $notification))->toBe($resolver->decide($user, $notification));
});

it('decides per recipient, not per notification', function () {
    $one = makeUser('one@example.com');
    $two = makeUser('two@example.com');
    storePreference($two, TestNotificationType::Invited, CoreChannel::Mail, false);

    $notification = notificationOf();
    $resolver = app(DeliveryResolver::class);

    expect($resolver->decide($one, $notification)->delivers(CoreChannel::Mail))->toBeTrue()
        ->and($resolver->decide($two, $notification)->delivers(CoreChannel::Mail))->toBeFalse();
});

it('reports what it dropped', function () {
    $user = makeUser();
    storePreference($user, TestNotificationType::Invited, CoreChannel::Mail, false);

    $decision = app(DeliveryResolver::class)->decide($user, notificationOf());

    expect($decision->dropped())->toBe([CoreChannel::Mail])
        ->and($decision->deferred())->toBe([])
        ->and($decision->announces())->toBeTrue();
});

it('treats an unsaved recipient as having no stored preferences', function () {
    $user = new User(['name' => 'Ghost', 'email' => 'ghost@example.com']);

    // No key means nothing was ever stored — and no query wasted on finding that out.
    expect(notificationOf(TestNotificationType::Invited)->via($user))
        ->toBe([InboxChannel::class, LiveChannel::class, 'mail']);
});

it('turns an unknown channel down in the single-channel re-check', function () {
    $user = makeUser();

    // ExtraChannel::Push is not in TestNotificationType::Removed's definition at all.
    expect(app(DeliveryResolver::class)
        ->decideChannel($user, TestNotificationType::Removed, ExtraChannel::Push)
        ->isDrop())->toBeTrue();
});

it('sends everything for a type that names no channels at all', function () {
    NotificationFacade::fake();

    $decision = app(DeliveryResolver::class)->decide(makeUser(), notificationOf(TestNotificationType::Removed));

    expect($decision->laravelChannels())->toBe([InboxChannel::class]);
});
