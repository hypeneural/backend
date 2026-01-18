<?php

namespace App\Policies;

use App\Models\Memory;
use App\Models\User;

class MemoryPolicy
{
    /**
     * Determine if user can view the memory.
     */
    public function view(User $user, Memory $memory): bool
    {
        // Owner can always view
        if ($memory->user_id === $user->id) {
            return true;
        }

        // Public memories
        if ($memory->visibility === 'public') {
            return true;
        }

        // Family visibility
        if ($memory->visibility === 'family' && $memory->family_id) {
            return $user->families->contains('id', $memory->family_id);
        }

        return false;
    }

    /**
     * Determine if user can update the memory.
     */
    public function update(User $user, Memory $memory): bool
    {
        return $memory->user_id === $user->id;
    }

    /**
     * Determine if user can delete the memory.
     */
    public function delete(User $user, Memory $memory): bool
    {
        return $memory->user_id === $user->id;
    }

    /**
     * Determine if user can react to the memory.
     */
    public function react(User $user, Memory $memory): bool
    {
        return $this->view($user, $memory);
    }

    /**
     * Determine if user can comment on the memory.
     */
    public function comment(User $user, Memory $memory): bool
    {
        return $this->view($user, $memory);
    }
}
