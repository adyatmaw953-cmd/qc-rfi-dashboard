<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

abstract class DatabaseNotificationBase extends Notification
{
    /**
     * @param array{
     *     type: string,
     *     title: string,
     *     body: string,
     *     url: string,
     *     rfi_id: int,
     *     rfi_no: string,
     *     finding_id: int|null,
     *     category: string,
     *     project_name: string,
     *     actor_name: string,
     *     actor_role: string,
     * } $payload
     */
    public function __construct(public array $payload) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }
}
