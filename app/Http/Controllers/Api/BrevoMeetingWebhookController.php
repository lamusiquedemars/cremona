<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBrevoMeetingWebhook;
use App\Models\OrganizationIntegration;
use App\Services\BrevoMeetingSynchronizer;
use Illuminate\Http\JsonResponse;

class BrevoMeetingWebhookController extends Controller
{
    public function __invoke(
        StoreBrevoMeetingWebhook $request,
        string $event,
        BrevoMeetingSynchronizer $synchronizer,
    ): JsonResponse {
        /** @var OrganizationIntegration $integration */
        $integration = $request->attributes->get('organization_integration');
        $appointment = $synchronizer->synchronize($event, $request->validated(), $integration);

        return response()->json([
            'data' => [
                'id' => $appointment->public_id,
                'status' => $appointment->status->value,
            ],
        ]);
    }
}
