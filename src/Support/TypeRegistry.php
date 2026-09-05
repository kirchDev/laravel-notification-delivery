<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Support;

use KirchDev\NotificationDelivery\Contracts\NotificationType;

/**
 * Every notification type the application declares, from the enums named in
 * config('notification-delivery.discovery.types').
 *
 * Registration is explicit rather than scanned: a filesystem scan of an application's enums is
 * both slow on every boot and wrong the moment a type lives in a package.
 */
final class TypeRegistry
{
    /**
     * @var list<NotificationType>|null
     */
    private ?array $types = null;

    /**
     * @return list<NotificationType>
     */
    public function all(): array
    {
        if ($this->types !== null) {
            return $this->types;
        }

        $types = [];

        foreach (self::configuredEnums() as $enum) {
            foreach ($enum::cases() as $case) {
                $types[] = $case;
            }
        }

        return $this->types = $types;
    }

    public function find(string $value): ?NotificationType
    {
        foreach ($this->all() as $type) {
            if ((string) $type->value === $value) {
                return $type;
            }
        }

        return null;
    }

    /**
     * @return list<class-string<NotificationType>>
     */
    private static function configuredEnums(): array
    {
        $configured = config('notification-delivery.discovery.types', []);

        if (! is_array($configured)) {
            return [];
        }

        $enums = [];

        foreach ($configured as $enum) {
            if (is_string($enum) && is_a($enum, NotificationType::class, true)) {
                $enums[] = $enum;
            }
        }

        return $enums;
    }
}
