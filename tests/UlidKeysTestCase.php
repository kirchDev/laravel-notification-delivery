<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Tests;

use Illuminate\Database\Eloquent\Model;
use KirchDev\NotificationDelivery\Tests\Fixtures\UlidUser;

abstract class UlidKeysTestCase extends TestCase
{
    protected function notificationKeyType(): string
    {
        return 'ulid';
    }

    /**
     * @return class-string<Model>
     */
    protected function userModelClass(): string
    {
        return UlidUser::class;
    }
}
