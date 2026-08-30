<?php

namespace App\Services;

use App\Models\ActionItem;
use App\Models\User;

class NotificationService
{
    public function create(User $user, string $title, ?string $message = null, string $kind = 'information', string $priority = 'normal', ?string $actionUrl = null, $dueAt = null, array $payload = []): ActionItem
    {
        return ActionItem::query()->create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'kind' => $kind,
            'priority' => $priority,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'due_at' => $dueAt,
            'payload' => $payload,
        ]);
    }
}
