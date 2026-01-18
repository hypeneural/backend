<?php

namespace App\Policies;

use App\Models\Experience;
use App\Models\User;

class ExperiencePolicy
{
    /**
     * Determine if user can view the experience.
     */
    public function view(User $user, Experience $experience): bool
    {
        // Published experiences can be viewed by anyone
        return $experience->status === 'published';
    }

    /**
     * Determine if user can review the experience.
     */
    public function review(User $user, Experience $experience): bool
    {
        // Only published experiences can be reviewed
        if ($experience->status !== 'published') {
            return false;
        }

        // User can only review once
        return !$experience->reviews()
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Determine if user can save the experience.
     */
    public function save(User $user, Experience $experience): bool
    {
        return $experience->status === 'published';
    }

    /**
     * Determine if user can report the experience.
     */
    public function report(User $user, Experience $experience): bool
    {
        return $experience->status === 'published';
    }
}
