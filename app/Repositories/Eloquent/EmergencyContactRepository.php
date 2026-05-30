<?php

namespace App\Repositories\Eloquent;

use App\Models\EmergencyContact;
use App\Repositories\Contracts\EmergencyContactRepositoryInterface;
use Illuminate\Support\Collection;

class EmergencyContactRepository implements EmergencyContactRepositoryInterface
{
    public function __construct(
        protected EmergencyContact $model
    ) {}

    /**
     * Get all active emergency contacts for a user.
     */
    public function getActiveContactsByUser(int $userId): Collection
    {
        return $this->model
            ->where('user_id', $userId)
            ->active()
            ->byPriority()
            ->get();
    }

    /**
     * Get priority contacts for a user.
     */
    public function getPriorityContacts(int $userId, int $limit = 3): Collection
    {
        return $this->model
            ->where('user_id', $userId)
            ->active()
            ->byPriority()
            ->limit($limit)
            ->get();
    }
}