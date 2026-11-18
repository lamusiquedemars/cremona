<?php

namespace App\Services;

use App\Models\Availability;
use App\Models\ClientSearch;
use App\Models\ClientSearchMatch;

class ClientSearchMatcher
{
    public function syncAvailability(Availability $availability): void
    {
        if ($availability->status !== 'available') {
            $availability->matches()->where('status', 'suggested')->delete();
            return;
        }

        $searches = ClientSearch::query()->where('status', 'open')->where('object_type', $availability->object_type);
        $availability->matches()->where('status', 'suggested')->whereNotIn('client_search_id', (clone $searches)->select('id'))->delete();
        $searches->each(fn (ClientSearch $search) => $this->syncPair($search, $availability));
    }

    public function syncSearch(ClientSearch $search): void
    {
        if ($search->status !== 'open') {
            $search->matches()->where('status', 'suggested')->delete();
            return;
        }

        $availabilities = Availability::query()->where('status', 'available')->where('object_type', $search->object_type);
        $search->matches()->where('status', 'suggested')->whereNotIn('availability_id', (clone $availabilities)->select('id'))->delete();
        $availabilities->each(fn (Availability $availability) => $this->syncPair($search, $availability));
    }

    private function syncPair(ClientSearch $search, Availability $availability): void
    {
        $kind = $this->kind($search, $availability);
        $existing = ClientSearchMatch::query()
            ->where('client_search_id', $search->id)->where('availability_id', $availability->id)->first();

        if ($kind === null) {
            if ($existing?->status === 'suggested') { $existing->delete(); }
            return;
        }

        if ($existing !== null && $existing->status !== 'suggested') {
            return;
        }

        ClientSearchMatch::query()->updateOrCreate(
            ['client_search_id' => $search->id, 'availability_id' => $availability->id],
            ['match_kind' => $kind, 'status' => 'suggested'],
        );
    }

    private function kind(ClientSearch $search, Availability $availability): ?string
    {
        $toReview = false;
        if ($search->size !== null) {
            if ($availability->size === null) { $toReview = true; }
            elseif ($search->size !== $availability->size) { return null; }
        }
        if ($search->maximum_budget !== null) {
            if ($availability->amount === null) { $toReview = true; }
            elseif ((float) $availability->amount > (float) $search->maximum_budget) { return null; }
        }
        return $toReview ? 'review' : 'match';
    }
}
