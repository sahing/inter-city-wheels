<?php

namespace App\Notifications;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlatformAlertNotification extends Notification
{
    /**
     * @param  array<string, mixed>  $meta
     * @param  list<string>  $lines
     */
    public function __construct(
        public readonly string $event,
        public readonly string $title,
        public readonly string $message,
        public readonly string $category = 'booking',
        public readonly array $meta = [],
        public readonly array $lines = [],
        public readonly ?string $actionLabel = null,
        public readonly ?string $actionUrl = null,
        public readonly bool $sendEmail = true,
    ) {}

    /**
     * Deliver in-app database notification first so mail transport issues
     * never prevent the in-app record from being persisted.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof AnonymousNotifiable) {
            return ['mail'];
        }

        return $this->sendEmail ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title.' — '.config('app.name', 'Intercity Wheels'))
            ->greeting('Hello'.(! empty($notifiable->name) ? ' '.$notifiable->name : '').',')
            ->line($this->message);

        foreach ($this->lines as $line) {
            if (is_string($line) && trim($line) !== '') {
                $mail->line($line);
            }
        }

        if ($this->actionLabel && $this->actionUrl) {
            $mail->action($this->actionLabel, $this->actionUrl);
        }

        return $mail->line('Thank you for travelling with '.config('app.name', 'Intercity Wheels').'.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => $this->event,
            'category' => $this->category,
            'title' => $this->title,
            'message' => $this->message,
            'meta' => $this->meta,
            'action_url' => $this->actionUrl,
            'action_label' => $this->actionLabel,
        ];
    }
}
