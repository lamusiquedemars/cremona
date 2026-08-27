<?php

namespace App\Http\Controllers\Api;

use App\Enums\IncomingRequestOutcome;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignDailyMetric;
use App\Models\IncomingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcquisitionSummaryController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'site_reference' => ['required', 'string', 'max:255'],
            'days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);
        $days = $data['days'] ?? 30;
        $since = now()->subDays($days);
        $campaigns = Campaign::query()
            ->where('site_reference', $data['site_reference'])
            ->withSum(['dailyMetrics as spend' => fn ($query) => $query->where('metric_date', '>=', $since->toDateString())], 'spend')
            ->orderBy('name')
            ->get();
        $trackingKeys = $campaigns->pluck('tracking_key');
        $requests = IncomingRequest::query()
            ->where('source_site_reference', $data['site_reference'])
            ->where('received_at', '>=', $since)
            ->whereIn('attribution_campaign', $trackingKeys);

        return response()->json([
            'data' => [
                'site_reference' => $data['site_reference'],
                'period_days' => $days,
                'generated_at' => now()->toIso8601String(),
                'currency' => $campaigns->pluck('currency')->unique()->count() === 1
                    ? $campaigns->value('currency')
                    : null,
                'spend' => (float) CampaignDailyMetric::query()
                    ->whereIn('campaign_id', $campaigns->pluck('id'))
                    ->where('metric_date', '>=', $since->toDateString())
                    ->sum('spend'),
                'leads' => (clone $requests)->count(),
                'converted_leads' => (clone $requests)
                    ->where('outcome', IncomingRequestOutcome::Converted)
                    ->count(),
                'campaigns' => $campaigns->map(function (Campaign $campaign) use ($data, $since): array {
                    $campaignRequests = IncomingRequest::query()
                        ->where('source_site_reference', $data['site_reference'])
                        ->where('attribution_campaign', $campaign->tracking_key)
                        ->where('received_at', '>=', $since);

                    return [
                        'name' => $campaign->name,
                        'tracking_key' => $campaign->tracking_key,
                        'channel' => $campaign->channel,
                        'status' => $campaign->status->value,
                        'spend' => (float) ($campaign->spend ?? 0),
                        'leads' => (clone $campaignRequests)->count(),
                        'converted_leads' => (clone $campaignRequests)
                            ->where('outcome', IncomingRequestOutcome::Converted)
                            ->count(),
                    ];
                })->values(),
            ],
        ]);
    }
}
