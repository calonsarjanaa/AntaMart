<?php

namespace App\Services;

use App\Models\User;

class AgeVerificationService
{
    /**
     * Check whether the current user (via profile birth_date) or session satisfies minAge.
     *
     * Returns:
     *  - 'verified': profile birth_date >= minAge OR manual session verified
     *  - 'underage': logged-in user has profile birth_date < minAge (cannot bypass)
     *  - 'unverified': no profile birth_date and no session verification yet
     */
    public function status(?User $user, int $minAge): string
    {
        if ($user && $user->hasBirthDate()) {
            return $user->meetsAgeRequirement($minAge) ? 'verified' : 'underage';
        }

        if (app()->bound('session') && session("age_verified_{$minAge}") === true) {
            return 'verified';
        }

        return 'unverified';
    }

    public function isVerified(?User $user, int $minAge): bool
    {
        return $this->status($user, $minAge) === 'verified';
    }
}

