<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery;

use Illuminate\Contracts\Foundation\Application;
use KirchDev\DeviceSessions\Models\UserDevice;
use KirchDev\NotificationDelivery\Console\PruneDeliveredNotificationsCommand;
use KirchDev\NotificationDelivery\Contracts\SuppressionPolicy;
use KirchDev\NotificationDelivery\Support\ChannelRegistry;
use KirchDev\NotificationDelivery\Support\DeliveryResolver;
use KirchDev\NotificationDelivery\Support\DeviceSessionSuppression;
use KirchDev\NotificationDelivery\Support\NeverSuppress;
use KirchDev\NotificationDelivery\Support\PreferenceResolver;
use KirchDev\NotificationDelivery\Support\TypeRegistry;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class NotificationDeliveryServiceProvider extends PackageServiceProvider
{
    /**
     * The package's shape: the config file, the command and the migrations.
     *
     * Migrations are discovered from database/migrations rather than listed here, so the
     * running order lives in the filenames and adding one is a single file. They are named
     * with Laravel's own sentinel date (0001_01_01_000001_create_delivered_notifications_table.php):
     * the publish strips that prefix and stamps its own, and in the meantime it keeps the source
     * files sorting in dependency order for the suite, which migrates them from the package
     * path. runsMigrations() stays off — consumers publish, the provider never auto-loads.
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-notification-delivery')
            ->hasConfigFile()
            ->hasCommands(PruneDeliveredNotificationsCommand::class)
            ->discoversMigrations();
    }

    /**
     * Skip the migration processing entirely outside the console.
     *
     * Upstream computes each published name — which globs the consumer's database/migrations —
     * before its own runningInConsole() check, so a plain HTTP request would pay a directory
     * scan on every boot. Nothing but vendor:publish needs that map while runsMigrations() is
     * off; if it is ever switched on, loadMigrationsFrom() has to run and the guard stands down.
     */
    protected function bootPackageMigrations(): PackageServiceProvider
    {
        if (! $this->app->runningInConsole() && ! $this->package->runsMigrations) {
            return $this;
        }

        return parent::bootPackageMigrations();
    }

    /**
     * Everything here is scoped rather than singleton: the consuming application runs Octane,
     * where a worker outlives the request, and all three of these cache per-request state — the
     * recipient's preference rows, and the gate chain's verdict for one notification.
     */
    public function packageRegistered(): void
    {
        $this->app->scoped(ChannelRegistry::class);
        $this->app->scoped(TypeRegistry::class);
        $this->app->scoped(PreferenceResolver::class);
        $this->app->scoped(DeliveryResolver::class);

        $this->registerSuppressionPolicy();
    }

    /**
     * Gate 4's binding, resolved in three steps when it is first needed: an explicitly
     * configured policy wins, otherwise the presence-aware one when device-sessions is
     * installed, otherwise nothing suppresses.
     *
     * A closure rather than a class name decided here, because the provider registers before
     * the application's own config is in place — under Testbench it registers before the test's
     * environment is defined at all. Reading the config at resolve time is the only way a
     * configured policy is honoured.
     */
    private function registerSuppressionPolicy(): void
    {
        $this->app->scoped(SuppressionPolicy::class, static function (Application $app): SuppressionPolicy {
            $configured = config('notification-delivery.suppression.policy');

            if (is_string($configured) && $configured !== '') {
                /** @var SuppressionPolicy $policy */
                $policy = $app->make($configured);

                return $policy;
            }

            // The optional integration, wired the same way device-sessions attaches its own
            // Fortify bridge: present the class, get the behaviour; absent it, get the default
            // and no error.
            if (class_exists(UserDevice::class)) {
                return $app->make(DeviceSessionSuppression::class);
            }

            return $app->make(NeverSuppress::class);
        });
    }
}
