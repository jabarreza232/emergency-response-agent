<?php

namespace App\Services;

use App\DTOs\EmergencyRequestDTO;
use App\Models\EmergencyLog;
use App\Models\User;
use App\Repositories\Contracts\EmergencyContactRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Emergency Response Service - AGENTIC CORE
 * 
 * This service contains autonomous rule-based logic for emergency response.
 * It makes intelligent decisions without human intervention or OpenAI API.
 */
class EmergencyResponseService
{
    // Rule-based thresholds
    const CRITICAL_DISTANCE_KM = 10.0;
    const WARNING_DISTANCE_KM = 25.0;
    const MAX_DISTANCE_KM = 50.0;
    const MIN_CONTACTS_TO_NOTIFY = 2;
    const MAX_CONTACTS_TO_NOTIFY = 5;
    const LOCATION_STALENESS_MINUTES = 30;

    public function __construct(
        protected GeolocationService $geolocationService,
        protected EmergencyContactRepositoryInterface $contactRepository,
        protected NotificationService $notificationService,
    ) {}

    /**
     * Process emergency trigger with autonomous decision-making.
     * This is the main agentic logic that runs without human intervention.
     */
    public function processEmergencyTrigger(EmergencyRequestDTO $request): array
    {
        $user = User::findOrFail($request->userId);
        
        // RULE 1: Validate location
        if (!$this->geolocationService->validateCoordinates($request->latitude, $request->longitude)) {
            throw new \InvalidArgumentException('Invalid GPS coordinates');
        }

        // RULE 2: Determine emergency severity based on context
        $severity = $this->determineSeverity($request);
        
        // RULE 3: Find nearest facilities with intelligent prioritization
        $facilities = $this->findPrioritizedFacilities($request, $severity);
        
        // RULE 4: Select contacts to notify based on severity
        $contacts = $this->selectContactsToNotify($request->userId, $severity);
        
        // RULE 5: Create emergency log with decision metadata
        $emergencyLog = $this->createEmergencyLog($request, $severity, $facilities, $contacts);
        
        // RULE 6: Execute notifications based on priority
        $notificationResults = $this->executeNotifications($user, $emergencyLog, $contacts, $facilities);
        
        // RULE 7: Update emergency log with notification results
        $this->updateEmergencyLogWithResults($emergencyLog, $notificationResults);

        return [
            'emergency_id' => $emergencyLog->id,
            'severity' => $severity,
            'facilities' => $facilities->map->toArray(),
            'contacts_notified' => $contacts->count(),
            'notification_results' => $notificationResults,
            'agent_decisions' => $emergencyLog->response_data,
        ];
    }

    /**
     * AUTONOMOUS RULE 1: Determine emergency severity.
     * Decision made based on multiple factors without human input.
     */
    protected function determineSeverity(EmergencyRequestDTO $request): string
    {
        $factors = [
            'has_emergency_type' => !empty($request->emergencyType),
            'emergency_type' => $request->emergencyType,
            'has_notes' => !empty($request->notes),
        ];

        // Critical severity keywords
        $criticalKeywords = ['cardiac', 'breathing', 'unconscious', 'bleeding', 'stroke', 'accident'];
        
        // High severity keywords
        $highKeywords = ['chest pain', 'severe pain', 'injury', 'fall', 'fever'];

        $severity = 'medium'; // default

        // Check emergency type for critical keywords
        if ($factors['has_emergency_type']) {
            $type = strtolower($request->emergencyType);
            
            foreach ($criticalKeywords as $keyword) {
                if (str_contains($type, $keyword)) {
                    return 'critical';
                }
            }
            
            foreach ($highKeywords as $keyword) {
                if (str_contains($type, $keyword)) {
                    $severity = 'high';
                }
            }
        }

        // Check notes for critical keywords
        if ($factors['has_notes']) {
            $notes = strtolower($request->notes);
            
            foreach ($criticalKeywords as $keyword) {
                if (str_contains($notes, $keyword)) {
                    return 'critical';
                }
            }
        }

        return $severity;
    }

    /**
     * AUTONOMOUS RULE 2: Find and prioritize facilities based on distance and capability.
     */
    protected function findPrioritizedFacilities(
        EmergencyRequestDTO $request,
        string $severity
    ): Collection {
        // Adjust search parameters based on severity
        $maxDistance = match($severity) {
            'critical' => self::MAX_DISTANCE_KM * 1.5, // Expand search for critical
            'high' => self::MAX_DISTANCE_KM,
            default => self::WARNING_DISTANCE_KM,
        };

        $maxResults = match($severity) {
            'critical' => 7,
            'high' => 5,
            default => 3,
        };

        $facilities = $this->geolocationService->findNearestFacilities(
            $request->latitude,
            $request->longitude,
            $maxResults,
            $maxDistance
        );

        // Log decision reasoning
        Log::info('Emergency Agent: Facility search', [
            'severity' => $severity,
            'max_distance' => $maxDistance,
            'facilities_found' => $facilities->count(),
        ]);

        return $facilities;
    }

    /**
     * AUTONOMOUS RULE 3: Select which contacts to notify based on severity.
     */
    protected function selectContactsToNotify(int $userId, string $severity): Collection
    {
        $allContacts = $this->contactRepository->getActiveContactsByUser($userId);

        // Determine how many contacts to notify
        $contactsToNotify = match($severity) {
            'critical' => min($allContacts->count(), self::MAX_CONTACTS_TO_NOTIFY),
            'high' => min($allContacts->count(), 3),
            default => min($allContacts->count(), self::MIN_CONTACTS_TO_NOTIFY),
        };

        return $allContacts->take($contactsToNotify);
    }

    /**
     * AUTONOMOUS RULE 4: Create emergency log with decision metadata.
     */
    protected function createEmergencyLog(
        EmergencyRequestDTO $request,
        string $severity,
        Collection $facilities,
        Collection $contacts
    ): EmergencyLog {
        $nearestFacility = $facilities->first();
        
        // Build agent decision log
        $agentDecisions = [
            'severity_determined' => $severity,
            'severity_reasoning' => $this->getSeverityReasoning($request, $severity),
            'facilities_found' => $facilities->count(),
            'nearest_facility_distance' => $nearestFacility?->distance ?? null,
            'distance_category' => $this->categorizeDistance($nearestFacility?->distance ?? 999),
            'contacts_selected' => $contacts->count(),
            'selection_strategy' => 'priority_based',
            'timestamp' => now()->toIso8601String(),
        ];

        return EmergencyLog::create([
            'user_id' => $request->userId,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'emergency_type' => $request->emergencyType,
            'notes' => $request->notes,
            'status' => 'triggered',
            'response_data' => $agentDecisions,
            'triggered_at' => now(),
        ]);
    }

    /**
     * AUTONOMOUS RULE 5: Execute notifications with intelligent prioritization.
     */
    protected function executeNotifications(
        User $user,
        EmergencyLog $log,
        Collection $contacts,
        Collection $facilities
    ): array {
        $results = [
            'contacts' => [],
            'facilities_included' => $facilities->count(),
        ];

        // Notify each contact
        foreach ($contacts as $contact) {
            try {
                $this->notificationService->notifyEmergencyContact(
                    $contact,
                    $user,
                    $log,
                    $facilities
                );
                
                $results['contacts'][] = [
                    'contact_id' => $contact->id,
                    'name' => $contact->name,
                    'status' => 'sent',
                ];
            } catch (\Exception $e) {
                $results['contacts'][] = [
                    'contact_id' => $contact->id,
                    'name' => $contact->name,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
                
                Log::error('Failed to notify emergency contact', [
                    'contact_id' => $contact->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Update emergency log with notification results.
     */
    protected function updateEmergencyLogWithResults(
        EmergencyLog $log,
        array $results
    ): void {
        $log->update([
            'contacted_entities' => $results,
            'status' => 'contacted',
        ]);
    }

    /**
     * Get human-readable severity reasoning.
     */
    protected function getSeverityReasoning(EmergencyRequestDTO $request, string $severity): string
    {
        if ($severity === 'critical') {
            return 'Critical keywords detected in emergency type or notes';
        }
        
        if ($severity === 'high') {
            return 'High-priority keywords detected';
        }
        
        return 'Standard emergency protocol - medium severity';
    }

    /**
     * Categorize distance for decision logging.
     */
    protected function categorizeDistance(?float $distance): string
    {
        if ($distance === null) {
            return 'no_facilities_found';
        }
        
        return match(true) {
            $distance <= self::CRITICAL_DISTANCE_KM => 'immediate_vicinity',
            $distance <= self::WARNING_DISTANCE_KM => 'nearby',
            $distance <= self::MAX_DISTANCE_KM => 'reachable',
            default => 'far',
        };
    }
}