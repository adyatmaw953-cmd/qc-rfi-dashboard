<?php

namespace App\Policies;

use App\Models\Ncr;
use App\Models\User;

class NcrPolicy
{
    /**
     * Determine whether the user's role is permitted for NCR module.
     */
    private function isNcrRole(User $user): bool
    {
        return in_array($user->role, config('mudalaya.modules.ncr.roles', ['admin', 'qc_qa']), true);
    }

    /**
     * Determine whether the user can view any NCRs.
     */
    public function viewAny(User $user): bool
    {
        return $this->isNcrRole($user);
    }

    /**
     * Determine whether the user can view the NCR.
     */
    public function view(User $user, Ncr $ncr): bool
    {
        return $this->isNcrRole($user);
    }

    /**
     * Determine whether the user can create NCRs.
     */
    public function create(User $user): bool
    {
        return $this->isNcrRole($user);
    }

    /**
     * Determine whether the user can update the NCR.
     */
    public function update(User $user, Ncr $ncr): bool
    {
        if ($ncr->status === 'Closed') {
            return false;
        }

        return $this->isNcrRole($user);
    }

    /**
     * Determine whether the user can delete the NCR.
     * Hapus (soft delete) untuk admin dan qc_qa.
     */
    public function delete(User $user, Ncr $ncr): bool
    {
        return $this->isNcrRole($user);
    }
}
