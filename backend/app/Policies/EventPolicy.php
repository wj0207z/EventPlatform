<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EventPolicy
{
    public function view(User $user, Event $event): bool
    {
        if ($user->role !== 'recruiter') {
            return false;
        }

        if (!$user->company_id || !$event->company_id) {
            return false;
        }

        return (int) $user->company_id === (int) $event->company_id;
    }

    public function update(User $user, Event $event): bool
    {
        return $this->view($user, $event);
    }
}
