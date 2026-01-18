<?php

namespace App\Policies;

use App\Models\Plan;
use App\Models\User;

class PlanPolicy
{
    /**
     * Determine if user can view the plan.
     */
    public function view(User $user, Plan $plan): bool
    {
        // Owner can always view
        if ($plan->user_id === $user->id) {
            return true;
        }

        // Collaborator can view
        if ($plan->hasCollaborator($user)) {
            return true;
        }

        // Family member can view if family visibility
        if ($plan->visibility === 'family' && $plan->family_id) {
            return $user->families->contains('id', $plan->family_id);
        }

        return false;
    }

    /**
     * Determine if user can update the plan.
     */
    public function update(User $user, Plan $plan): bool
    {
        // Owner can always update
        if ($plan->user_id === $user->id) {
            return true;
        }

        // Check if collaborator with edit role
        $collaborator = $plan->collaborators()
            ->where('user_id', $user->id)
            ->first();

        return $collaborator && $collaborator->canEdit();
    }

    /**
     * Determine if user can delete the plan.
     */
    public function delete(User $user, Plan $plan): bool
    {
        return $plan->user_id === $user->id;
    }

    /**
     * Determine if user can manage experiences in the plan.
     */
    public function manageExperiences(User $user, Plan $plan): bool
    {
        return $this->update($user, $plan);
    }

    /**
     * Determine if user can invite collaborators.
     */
    public function inviteCollaborator(User $user, Plan $plan): bool
    {
        return $plan->user_id === $user->id;
    }

    /**
     * Determine if user can add memories to the plan.
     */
    public function addMemory(User $user, Plan $plan): bool
    {
        return $this->view($user, $plan);
    }
}
