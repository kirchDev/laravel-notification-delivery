<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\RoutesNotifications;
use KirchDev\NotificationDelivery\Concerns\HasNotificationDelivery;

/**
 * The shape a consumer's recipient takes: RoutesNotifications plus this package's trait, never
 * Laravel's Notifiable — which would drag HasDatabaseNotifications and its empty relation in.
 */
class User extends Authenticatable
{
    use HasNotificationDelivery;
    use RoutesNotifications;

    protected $table = 'users';

    /**
     * @var list<string>
     */
    protected $guarded = [];
}
