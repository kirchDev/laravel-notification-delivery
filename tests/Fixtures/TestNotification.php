<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Tests\Fixtures;

use Illuminate\Notifications\Messages\MailMessage;
use KirchDev\NotificationDelivery\Contracts\NotificationType;
use KirchDev\NotificationDelivery\Notifications\TypedNotification;
use KirchDev\NotificationDelivery\Support\ActionData;
use KirchDev\NotificationDelivery\Support\PayloadData;

class TestNotification extends TypedNotification
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        private readonly NotificationType $type,
        private readonly array $arguments = [],
    ) {}

    public function type(): NotificationType
    {
        return $this->type;
    }

    public function payload(object $notifiable): PayloadData
    {
        return new PayloadData(
            name: 'notification.'.$this->type->value,
            payload: $this->arguments,
            actions: [new ActionData('open', ['id' => 1], 'action.open')],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->line('notification.'.$this->type->value);
    }
}
