<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use KirchDev\NotificationDelivery\Models\DeliveredNotification;
use KirchDev\NotificationDelivery\NotificationDelivery;

/**
 * Delete stored notifications past their retention window.
 *
 * Two windows, because read notifications go stale faster than unread ones — and both default to
 * null, meaning never delete. A device revoked half a year ago interests nobody; a record of who
 * was invited when can be the reason somebody still has access years later. Deciding that for
 * every consumer is not the package's call.
 *
 * Ships unscheduled, exactly like device-sessions:prune. Wire it into the application's scheduler.
 */
class PruneDeliveredNotificationsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'notification-delivery:prune
        {--days= : Retention period in days for every notification (defaults to config)}
        {--read-days= : Shorter retention period for notifications that have been read}';

    /**
     * @var string
     */
    protected $description = 'Delete stored notifications past their retention window.';

    public function handle(): int
    {
        try {
            $days = $this->window('days', 'retention_days');
            $readDays = $this->window('read-days', 'retention_days_read');
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($days === null && $readDays === null) {
            $this->info('No retention window configured — nothing pruned.');

            return self::SUCCESS;
        }

        $deleted = 0;

        if ($readDays !== null) {
            $deleted += $this->prune(static fn ($query) => $query
                ->whereNotNull('read_at')
                ->where('read_at', '<=', now()->subDays($readDays)));
        }

        if ($days !== null) {
            $deleted += $this->prune(static fn ($query) => $query
                ->where('created_at', '<=', now()->subDays($days)));
        }

        $this->info(sprintf('Pruned %d stored notifications.', $deleted));

        return self::SUCCESS;
    }

    /**
     * Delete in chunks of ids rather than one big DELETE: an inbox table is the one that grows
     * without bound, and a single statement over months of rows is how a prune job locks a
     * production table.
     *
     * @param  callable(Builder<DeliveredNotification>): Builder<DeliveredNotification>  $constrain
     */
    private function prune(callable $constrain): int
    {
        $model = NotificationDelivery::notificationModel();
        $chunk = 1000;
        $deleted = 0;

        do {
            $ids = $constrain($model::query())->limit($chunk)->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += $model::query()->whereKey($ids)->delete();
        } while ($ids->count() === $chunk);

        return $deleted;
    }

    /**
     * A window in days, from the option or the config, or null for "never delete".
     *
     * A negative window is refused rather than clamped. Clamping it to zero would read as
     * "delete everything older than right now", which is the one outcome a typo in a retention
     * setting must never produce.
     *
     * @throws InvalidArgumentException
     */
    private function window(string $option, string $configKey): ?int
    {
        $value = $this->option($option);

        if (! is_numeric($value)) {
            $value = config('notification-delivery.prune.'.$configKey);
        }

        if (! is_numeric($value)) {
            return null;
        }

        $days = (int) $value;

        if ($days < 0) {
            throw new InvalidArgumentException(
                sprintf('A retention window must not be negative, got [%d] for --%s.', $days, $option),
            );
        }

        return $days;
    }
}
