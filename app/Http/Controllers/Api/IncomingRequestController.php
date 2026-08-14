<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIncomingRequest;
use App\Services\IncomingRequestManager;
use Illuminate\Http\JsonResponse;

class IncomingRequestController extends Controller
{
    public function store(
        StoreIncomingRequest $request,
        IncomingRequestManager $manager,
    ): JsonResponse {
        $data = $request->validated();
        $incomingRequest = $manager->receive([
            'idempotency_key' => $data['idempotency_key'],
            'source_channel' => $data['source']['channel'],
            'source' => $data['source']['name'] ?? null,
            'source_site_reference' => $data['source']['site_reference'] ?? null,
            'source_form_reference' => $data['source']['form_reference'] ?? null,
            'attribution_source' => $data['attribution']['source'] ?? null,
            'attribution_medium' => $data['attribution']['medium'] ?? null,
            'attribution_campaign' => $data['attribution']['campaign'] ?? null,
            'name_snapshot' => $data['contact']['name'] ?? null,
            'email_snapshot' => $data['contact']['email'] ?? null,
            'phone_snapshot' => $data['contact']['phone'] ?? null,
            'subject' => $data['request']['subject'] ?? null,
            'message' => $data['request']['message'],
            'category' => $data['request']['category'] ?? null,
            'urgency' => $data['request']['urgency'] ?? 'unknown',
            'important_date' => $data['request']['important_date'] ?? null,
            'answers' => collect($data['answers'] ?? [])->map(fn (array $answer): array => [
                'field_key' => $answer['field_key'],
                'label_snapshot' => $answer['label'],
                'value' => $answer['value'] ?? null,
                'value_type' => $answer['value_type'] ?? 'text',
                'position' => $answer['position'] ?? 0,
            ])->all(),
            'consent' => isset($data['consent']) ? [
                'purpose' => $data['consent']['purpose'],
                'channel' => $data['consent']['channel'] ?? 'unspecified',
                'status' => $data['consent']['status'],
                'statement_snapshot' => $data['consent']['statement'],
                'statement_version' => $data['consent']['statement_version'] ?? null,
                'source' => $data['consent']['source'] ?? null,
                'granted_at' => $data['consent']['granted_at'] ?? null,
            ] : null,
        ]);

        return response()->json([
            'data' => [
                'id' => $incomingRequest->public_id,
                'status' => $incomingRequest->status->value,
                'received_at' => $incomingRequest->received_at->toIso8601String(),
            ],
        ], $incomingRequest->wasRecentlyCreated ? 201 : 200);
    }
}
