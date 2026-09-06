<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Tests;

use Illuminate\Database\Eloquent\Model;
use KirchDev\NotificationDelivery\Tests\Fixtures\UuidUser;

abstract class UuidKeysTestCase extends TestCase
{
    protected function notificationKeyType(): string
    {
        return 'uuid';
    }

    /**
     * @return class-string<Model>
     */
    protected function userModelClass(): string
    {
        return UuidUser::class;
    }
}
