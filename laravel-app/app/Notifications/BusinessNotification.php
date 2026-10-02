<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BusinessNotification extends Notification
{
    public function __construct(public string $type, public array $payload = []) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$subject, $line, $action] = match ($this->type) {
            'contractor.profile_changed' => ['Contractor profile requires review', 'A contractor profile or service selection changed and is awaiting review.', 'Review contractor'],
            'contractor.approval_changed' => ['Your NestCrafters approval status changed', 'Your contractor approval status was updated. Sign in to see the current status.', 'View account status'],
            'job.assigned' => ['A job was assigned to you', 'A NestCrafters job is ready for your review and acceptance.', 'View job'],
            'job.updated' => ['A NestCrafters job was updated', 'A job assigned to you has new details. Review the current terms before continuing.', 'View job'],
            'job.accepted' => ['A NestCrafters job was accepted', 'A contractor accepted a job assigned through NestCrafters.', 'View job'],
            'job.completed' => ['A NestCrafters job was completed', 'A job assigned through NestCrafters was marked completed.', 'View job'],
            'job.note_added' => ['New note on a NestCrafters job', 'A permitted participant added a note to a job you can access.', 'View job'],
            'job.media_uploaded' => ['New media on a NestCrafters job', 'A permitted participant uploaded media to a job you can access.', 'View job'],
            'document.expiring' => ['A contractor document is expiring', 'An active insurance document is within its reminder window.', 'Review documents'],
            default => ['NestCrafters update', 'There is a new update in the contractor portal.', 'Open NestCrafters'],
        };

        $message = (new MailMessage)->subject($subject)->greeting('Hello,')->line($line);
        if (! empty($this->payload['url'])) {
            $message->action($action, $this->payload['url']);
        }

        return $message;
    }
}
