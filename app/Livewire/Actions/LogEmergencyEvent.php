<?php

namespace App\Actions\EmergencyResponse;

use App\DTOs\EmergencyRequestDTO;
use App\Models\EmergencyLog;

/**
 * Action: Log Emergency Event
 * 
 * Single-purpose action to create emergency event logs.
 */
class LogEmergencyEvent
{
    /**
     * Execute the action.
     */
    public function execute(
        EmergencyRequestDTO $request,
        array $agentDecisions = []
    ): EmergencyLog {
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
     * Update emergency log status.
     */
    public function updateStatus(EmergencyLog $log, string $status, array $additionalData = []): void
    {
        $updateData = ['status' => $status];

        if ($status === 'resolved' || $status === 'cancelled') {
            $updateData['resolved_at'] = now();
        }

        if (!empty($additionalData)) {
            $updateData['response_data'] = array_merge(
                $log->response_data ?? [],
                $additionalData
            );
        }

        $log->update($updateData);
    }
}