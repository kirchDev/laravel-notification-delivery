<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Contracts;

use BackedEnum;

/**
 * A coarse bucket a notification type belongs to — "organisation", "billing", "security".
 *
 * It exists for one reason: the middle tier of preference resolution. A recipient who switches
 * mail off for a whole group needs a key to store that against, and `group:<value>` is that key.
 * The package needs nothing else from a group, so the interface adds nothing to `BackedEnum` —
 * an application's own enum brings labels, icons and ordering.
 *
 * Grouping is optional. A type whose `group()` returns null simply resolves on two tiers.
 */
interface NotificationGroup extends BackedEnum {}
