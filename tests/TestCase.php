<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use KirchDev\NotificationDelivery\NotificationDeliveryServiceProvider;
use KirchDev\NotificationDelivery\Support\NeverSuppress;
use KirchDev\NotificationDelivery\Tests\Fixtures\Notification\TestNotificationType;
use KirchDev\NotificationDelivery\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [NotificationDeliveryServiceProvider::class];
    }

    /**
     * Key type under test (id / uuid / ulid). Variants override this.
     */
    protected function notificationKeyType(): string
    {
        return 'id';
    }

    /**
     * @return class-string<Model>
     */
    protected function userModelClass(): string
    {
        return User::class;
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', $this->databaseConfig());

        $app['config']->set('auth.defaults.guard', 'web');
        $app['config']->set('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);
        $app['config']->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => $this->userModelClass(),
        ]);

        // Every block is set whole. mergeConfigFrom() merges the package defaults only at the
        // top level, so setting one nested key here would drop the rest of that block.
        $app['config']->set('notification-delivery.keys', [
            'primary_key_type' => $this->notificationKeyType(),
            'notifiable_morph_key_type' => $this->notificationKeyType(),
        ]);

        // laravel-device-sessions is a dev dependency, so the auto-binding would hand every test
        // the presence-aware policy. Gate 4 is pinned to "never" here and named explicitly by
        // the tests that are about it.
        $app['config']->set('notification-delivery.suppression', [
            'policy' => NeverSuppress::class,
            'presence_window' => 300,
            'defer' => 120,
        ]);

        $app['config']->set('notification-delivery.discovery', [
            'types' => [TestNotificationType::class],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function databaseConfig(): array
    {
        $driver = getenv('DB_CONNECTION') ?: 'sqlite';

        return match ($driver) {
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => getenv('DB_HOST') ?: '127.0.0.1',
                'port' => getenv('DB_PORT') ?: '5432',
                'database' => getenv('DB_DATABASE') ?: 'notification_delivery_test',
                'username' => getenv('DB_USERNAME') ?: 'notification_delivery',
                'password' => getenv('DB_PASSWORD') ?: 'notification_delivery',
                'prefix' => '',
            ],
            default => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        };
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->restoreBaselineSchema();
    }

    /**
     * The schema every test starts from: a clean database, the host users table and the
     * package's own migrations.
     *
     * A test that tears this down — migrate:fresh drops users too — calls this again rather than
     * re-running the package path alone.
     */
    protected function restoreBaselineSchema(): void
    {
        // Persistent drivers (e.g. pgsql) keep tables between tests, unlike the
        // fresh-per-connection in-memory SQLite default. Drop everything first so
        // each test starts from a clean schema.
        Schema::dropAllTables();

        $this->createUsersTable();
        $this->migratePackageTables();
    }

    /**
     * The package is publish-only: the service provider does not register its migration
     * path, so the suite points `migrate` at it explicitly. A consumer gets the same
     * migration contents from `vendor:publish --tag=notification-delivery-migrations`, under
     * filenames stamped at publish time; here the source prefixes supply the order.
     */
    private function migratePackageTables(): void
    {
        $this->artisan('migrate', [
            '--path' => __DIR__.'/../database/migrations',
            '--realpath' => true,
        ])->run();
    }

    private function createUsersTable(): void
    {
        $keyType = $this->notificationKeyType();

        Schema::create('users', function (Blueprint $table) use ($keyType): void {
            match ($keyType) {
                'uuid' => $table->uuid('id')->primary(),
                'ulid' => $table->ulid('id')->primary(),
                default => $table->id(),
            };
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });
    }
}
