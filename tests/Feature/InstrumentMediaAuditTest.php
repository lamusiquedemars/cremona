<?php

namespace Tests\Feature;

use App\Models\InstrumentAsset;
use App\Models\Organization;
use App\Models\OrganizationAuditLog;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstrumentMediaAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_photo_additions_removals_and_changes_are_audited_with_the_actor(): void
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->create();
        $this->actingAs($actor);

        app(OrganizationContext::class)->run($organization, function () use ($actor): void {
            $instrument = InstrumentAsset::query()->create([
                'name' => 'Violon d’essai',
                'media' => [[
                    'path' => 'instruments/violon-face.jpg',
                    'caption' => 'Face',
                    'is_public' => true,
                ]],
            ]);

            $instrument->update([
                'media' => [
                    ['path' => 'instruments/violon-face.jpg', 'caption' => 'Face restaurée', 'is_public' => false],
                    ['path' => 'instruments/violon-dos.jpg', 'caption' => 'Dos', 'is_public' => true],
                ],
            ]);

            $instrument->update(['media' => []]);

            $events = OrganizationAuditLog::query()
                ->where('subject_type', $instrument->getMorphClass())
                ->where('subject_id', $instrument->getKey())
                ->orderBy('id')
                ->get();

            $this->assertSame([
                'instrument_media.photo_added',
                'instrument_media.photo_added',
                'instrument_media.photo_updated',
                'instrument_media.photo_removed',
                'instrument_media.photo_removed',
            ], $events->pluck('event')->all());
            $this->assertTrue($events->every(fn (OrganizationAuditLog $event): bool => $event->actor_user_id === $actor->id));
            $this->assertSame('instruments/violon-face.jpg', $events->first()->metadata['photo_path']);
            $this->assertSame('Face', $events->first()->metadata['after']['caption']);
            $this->assertSame('Face restaurée', $events->get(2)->metadata['changes']['caption']['after']);
        });
    }
}
