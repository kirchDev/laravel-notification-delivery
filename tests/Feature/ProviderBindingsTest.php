<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\ServiceProvider;
use KirchDev\NotificationDelivery\Contracts\SuppressionPolicy;
use KirchDev\NotificationDelivery\NotificationDeliveryServiceProvider;
use KirchDev\NotificationDelivery\Support\ChannelRegistry;
use KirchDev\NotificationDelivery\Support\DeliveryResolver;
use KirchDev\NotificationDelivery\Support\DeviceSessionSuppression;
use KirchDev\NotificationDelivery\Support\NeverSuppress;
use KirchDev\NotificationDelivery\Support\PreferenceResolver;
use KirchDev\NotificationDelivery\Support\TypeRegistry;

it('binds the per-request services scoped, not singleton', function () {
    // The consuming application runs Octane, where a worker outlives the request — and a cache
    // that never resets is a cache that eventually answers for the wrong recipient.
    foreach ([ChannelRegistry::class, TypeRegistry::class, PreferenceResolver::class, DeliveryResolver::class] as $service) {
        $first = app($service);

        expect(app($service))->toBe($first);

        app()->forgetScopedInstances();

        expect(app($service))->not->toBe($first);
    }
});

it('binds the configured policy', function () {
    config()->set('notification-delivery.suppression.policy', NeverSuppress::class);
    app()->forgetInstance(SuppressionPolicy::class);
    app()->forgetScopedInstances();

    expect(app(SuppressionPolicy::class))->toBeInstanceOf(NeverSuppress::class);
});

it('upgrades to the presence-aware policy when device-sessions is installed', function () {
    config()->set('notification-delivery.suppression.policy', null);
    app()->forgetInstance(SuppressionPolicy::class);
    app()->forgetScopedInstances();

    expect(app(SuppressionPolicy::class))->toBeInstanceOf(DeviceSessionSuppression::class);
});

it('registers the prune command', function () {
    expect(array_keys(app(Kernel::class)->all()))
        ->toContain('notification-delivery:prune');
});

it('publishes the config under the package tag', function () {
    $published = ServiceProvider::pathsToPublish(
        NotificationDeliveryServiceProvider::class,
        'notification-delivery-config',
    );

    expect(array_values($published))->toBe([config_path('notification-delivery.php')]);
});
