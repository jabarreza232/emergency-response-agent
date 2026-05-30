<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface EmergencyContactRepositoryInterface
{
    /**
     * Get all active emergency contacts for a user.
     *
     * @param int $userId
     * @return Collection
     */
    public function getActiveContactsByUser(int $userId): Collection;

    /**
     * Get priority contacts for a user.
     *
     * @param int $userId
     * @param int $limit
     * @return Collection
     */
    public function getPriorityContacts(int $userId, int $limit = 3): Collection;
}