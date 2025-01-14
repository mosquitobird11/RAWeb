<?php

declare(strict_types=1);

namespace App\Community\Policies;

use App\Community\Models\TriggerTicket;
use App\Site\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TriggerTicketPolicy
{
    use HandlesAuthorization;

    public function view(User $user, TriggerTicket $achievementTicket): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return $user->email_verified_at !== null;
    }

    public function update(User $user, TriggerTicket $achievementTicket): bool
    {
        return false;
    }

    public function delete(User $user, TriggerTicket $achievementTicket): bool
    {
        return false;
    }

    public function restore(User $user, TriggerTicket $achievementTicket): bool
    {
        return false;
    }

    public function forceDelete(User $user, TriggerTicket $achievementTicket): bool
    {
        return false;
    }
}
