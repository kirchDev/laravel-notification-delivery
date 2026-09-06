<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\RoutesNotifications;
use KirchDev\NotificationDelivery\Concerns\HasNotificationDelivery;

class UlidUser extends Authenticatable
{
    use HasNotificationDelivery;
    use HasUlids;
    use RoutesNotifications;

    protected $table = 'users';

    /**
     * @var list<string>
     */
    protected $guarded = [];
}
