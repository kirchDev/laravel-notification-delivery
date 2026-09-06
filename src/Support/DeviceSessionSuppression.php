<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Support;

use Illuminate\Database\Eloquent\Model;
use KirchDev\DeviceSessions\Support\DeviceSessions;
use KirchDev\NotificationDelivery\Contracts\Channel;
use KirchDev\NotificationDelivery\Contracts\NotificationType;
use KirchDev\NotificationDelivery\Contracts\SuppressionPolicy;

/**
 * A presence-aware gate 4, bound only when kirchdev/laravel-device-sessions is installed.
 *
 * Someone with a device seen seconds ago is looking at the application right now, so a mail is
 * held back long enough for them to see the toast and read it. The job that runs when the delay
 * expires discards the mail if the notification has been read by then, and sends it otherwise.
 *
 * It reads `user_devices.last_seen_at` and never `users.last_seen_at`: that column belongs to the
 * application, is written by a listener some projects have and others do not, and a policy that
 * depended on it would silently never suppress in the ones that do not.
 *
 * This is a default, not a constraint. The contract stands and an application rebinds it freely.
 */
final class DeviceSessionSuppression implements SuppressionPolicy
{
    public function decide(object $notifiable, NotificationType $type, Channel $channel): SuppressionDecision
    {
        if (! $channel->isQuietable()) {
            return SuppressionDecision::send();
        }

        if (! $this->isPresent($notifiable)) {
            return SuppressionDecision::send();
        }

        return SuppressionDecision::defer(self::configuredSeconds('defer', 120));
    }

    private function isPresent(object $notifiable): bool
    {
        if (! $notifiable instanceof Model) {
            return false;
        }

        $window = self::configuredSeconds('presence_window', 300);

        return DeviceSessions::deviceModel()::query()
            ->where(DeviceSessions::userForeignKey(), $notifiable->getKey())
            ->whereNull('revoked_at')
            ->where('last_seen_at', '>=', now()->subSeconds($window))
            ->exists();
    }

    private static function configuredSeconds(string $key, int $fallback): int
    {
        $value = config('notification-delivery.suppression.'.$key, $fallback);

        return is_numeric($value) ? (int) $value : $fallback;
    }
}
