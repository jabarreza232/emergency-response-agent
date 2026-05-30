<?php

namespace App\Actions\EmergencyResponse;

use App\Models\EmergencyLog;
use App\Models\User;
use App\Repositories\Contracts\EmergencyContactRepositoryInterface;
use App\Services\NotificationService;
use Illuminate\Support\Collection;

/**
 * Action: Send Emergency Alert
 * 
 * Single-purpose action to send emergency alerts to contacts.
 */
class SendEmergencyAlert
{
    public function __construct(
        protected EmergencyContactRepositoryInterface $contactRepository,
        protected NotificationService $notificationService
    ) {}

    /**
     * Execute the action.
     */
    public function execute(
        User $user,
        EmergencyLog $log,
        Collection $facilities,
        int $maxContacts = 3
    ): array {
        $contacts = $this->contactRepository->getPriorityContacts(
            $user->id,
            $maxContacts
        );

        $results = [];

        foreach ($contacts as $contact) {
            try {
                $this->notificationService->notifyEmergencyContact(
                    $contact,
                    $user,
                    $log,
                    $facilities
                );
                
                $results[] = [
                    'contact_id' => $contact->id,
                    'name' => $contact->name,
                    'status' => 'sent',
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'contact_id' => $contact->id,
                    'name' => $contact->name,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}