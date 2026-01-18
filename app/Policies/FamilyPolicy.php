<?php

namespace App\Policies;

use App\Models\Family;
use App\Models\FamilyUser;
use App\Models\User;

class FamilyPolicy
{
    /**
     * Determine if user can view the family.
     */
    public function view(User $user, Family $family): bool
    {
        return $family->hasUser($user);
    }

    /**
     * Determine if user can update the family.
     */
    public function update(User $user, Family $family): bool
    {
        $familyUser = FamilyUser::where('family_id', $family->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        return $familyUser && $familyUser->isAdmin();
    }

    /**
     * Determine if user can manage members (invite/remove).
     */
    public function manageMembers(User $user, Family $family): bool
    {
        return $this->update($user, $family);
    }

    /**
     * Determine if user can delete the family.
     */
    public function delete(User $user, Family $family): bool
    {
        $familyUser = FamilyUser::where('family_id', $family->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        return $familyUser && $familyUser->isOwner();
    }

    /**
     * Determine if user can manage dependents.
     */
    public function manageDependents(User $user, Family $family): bool
    {
        return $family->hasUser($user);
    }

    /**
     * Determine if user can update preferences.
     */
    public function updatePreferences(User $user, Family $family): bool
    {
        return $family->hasUser($user);
    }
}
