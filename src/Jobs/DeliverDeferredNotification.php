<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use KirchDev\NotificationDelivery\Contracts\Channel;
use KirchDev\NotificationDelivery\NotificationDelivery;
use KirchDev\NotificationDelivery\Notifications\TypedNotification;
use KirchDev\NotificationDelivery\Support\DeliveryResolver;

/**
 * One channel the SuppressionPolicy asked to hold back, delivered — or discarded — when its
 * delay expires.
 *
 * The `if_unread` rule lives here, and it is what makes "inbox always, live when online,
 * otherwise mail" fall out of the gates with no escalation logic anywhere: whoever saw the toast
 * has read the notification by now, and whoever was away has not.
 *
 * The SuppressionPolicy is not consulted a second time: the hold was its verdict. When the delay
 * expires an unread notification is delivered and a read one is discarded, and only gates 1–3 —
 * a channel that became unavailable, or one the recipient switched off meanwhile — can still
 * turn it down.
 *
 * It sends through sendNow() with an explicit channel list rather than re-sending the
 * notification, because re-running via() would write a second inbox row for something that was
 * already delivered.
 */
class DeliverDeferredNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly object $notifiable,
        public readonly TypedNotification $notification,
        public readonly Channel $channel,
    ) {}

    public function handle(DeliveryResolver $resolver): void
    {
        if ($this->wasRead()) {
            return;
        }

        $decision = $resolver->decideChannel($this->notifiable, $this->notification->type(), $this->channel);

        // Gates 1–3 only: the policy already spent its say on the hold, so the only verdicts left
        // are "still unavailable" and "switched off in the meantime".
        if (! $decision->isSend()) {
            return;
        }

        NotificationFacade::sendNow($this->notifiable, $this->notification, [$this->channel->laravelChannel()]);
    }

    /**
     * Whether the recipient has already read this notification.
     *
     * Read against the newest stored row for this (notifiable, type): the row this delivery
     * created, unless a second notification of the same type arrived in the meantime — in which
     * case reading that one is just as good an answer to "did they see it".
     *
     * A type that does not write to the inbox has no row, and no row is not read.
     */
    private function wasRead(): bool
    {
        $key = NotificationDelivery::morphKeyFor($this->notifiable);

        if ($key === null) {
            return false;
        }

        $latest = NotificationDelivery::notificationModel()::query()
            ->forNotifiable($this->notifiable)
            ->where('type', NotificationDelivery::typeKey($this->notification->type()))
            ->latest('id')
            ->first();

        return $latest !== null && $latest->isRead();
    }
}
