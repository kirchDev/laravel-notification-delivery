<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\RoutesNotifications;
use KirchDev\NotificationDelivery\Concerns\HasNotificationDelivery;

class UuidUser extends Authenticatable
{
    use HasNotificationDelivery;
    use HasUuids;
    use RoutesNotifications;

    protected $table = 'users';

    /**
     * @var list<string>
     */
    protected $guarded = [];
}
