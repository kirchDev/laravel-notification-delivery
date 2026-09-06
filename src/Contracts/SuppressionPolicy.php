<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Contracts;

use KirchDev\NotificationDelivery\Support\SuppressionDecision;

/**
 * Gate 4: is this a bad moment for this channel?
 *
 * The one gate the package does not own. Deciding what "quiet" means would force it to know
 * about device sessions, presence, working hours and time zones, so it ships NeverSuppress and
 * the application binds its own — the same pattern as IpMasker in laravel-device-sessions or
 * OrganisationResolver in laravel-pbac.
 *
 * Only channels whose isQuietable() is true reach this gate.
 */
interface SuppressionPolicy
{
    public function decide(object $notifiable, NotificationType $type, Channel $channel): SuppressionDecision;
}
