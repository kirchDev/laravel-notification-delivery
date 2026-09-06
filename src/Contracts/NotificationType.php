<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Contracts;

use BackedEnum;
use KirchDev\NotificationDelivery\Support\NotificationDefinition;

/**
 * A notification type, as an enum case with a stable string key.
 *
 * Laravel's own database channel stores the fully-qualified class name, and a class that moves
 * leaves historical rows pointing nowhere. A type key is a value the application owns and never
 * has to refactor: 'organisation.member.invited' survives every rename of the class that sends it.
 */
interface NotificationType extends BackedEnum
{
    /**
     * The group this enum's cases belong to, or null when the application does not group.
     *
     * Static on purpose: the group belongs to the enum, not to the case. One enum per group is
     * what makes a group-level settings row meaningful.
     */
    public static function group(): ?NotificationGroup;

    /**
     * Which channels this type knows, which are on by default, and which cannot be switched off.
     *
     * An application that wants to carry more per type returns a subclass of
     * NotificationDefinition — PHP's covariant return types make that work without a second
     * interface.
     */
    public function definition(): NotificationDefinition;
}
