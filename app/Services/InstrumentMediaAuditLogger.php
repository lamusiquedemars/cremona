<?php

namespace App\Services;

use App\Models\InstrumentAsset;
use App\Models\User;
use App\Tenancy\OrganizationContext;

final class InstrumentMediaAuditLogger
{
    /** @param array<int, mixed> $before @param array<int, mixed> $after */
    public function recordChanges(InstrumentAsset $instrument, array $before, array $after, ?User $actor = null): void
    {
        $beforeByPath = $this->byPath($before);
        $afterByPath = $this->byPath($after);

        app(OrganizationContext::class)->run($instrument->organization, function () use ($instrument, $beforeByPath, $afterByPath, $actor): void {
            foreach (array_diff_key($afterByPath, $beforeByPath) as $path => $photo) {
                $this->record('instrument_media.photo_added', $instrument, $actor, $path, ['after' => $photo]);
            }

            foreach (array_diff_key($beforeByPath, $afterByPath) as $path => $photo) {
                $this->record('instrument_media.photo_removed', $instrument, $actor, $path, ['before' => $photo]);
            }

            foreach (array_intersect_key($afterByPath, $beforeByPath) as $path => $photo) {
                $changes = [];

                foreach (['caption', 'is_public'] as $field) {
                    if (($beforeByPath[$path][$field] ?? null) !== ($photo[$field] ?? null)) {
                        $changes[$field] = [
                            'before' => $beforeByPath[$path][$field] ?? null,
                            'after' => $photo[$field] ?? null,
                        ];
                    }
                }

                if ($changes !== []) {
                    $this->record('instrument_media.photo_updated', $instrument, $actor, $path, ['changes' => $changes]);
                }
            }
        });
    }

    /** @param array<int, mixed> $media @return array<string, array{caption: ?string, is_public: bool}> */
    private function byPath(array $media): array
    {
        return collect($media)
            ->filter(fn (mixed $photo): bool => is_array($photo) && filled($photo['path'] ?? null))
            ->mapWithKeys(fn (array $photo): array => [
                (string) $photo['path'] => [
                    'caption' => filled($photo['caption'] ?? null) ? (string) $photo['caption'] : null,
                    'is_public' => (bool) ($photo['is_public'] ?? false),
                ],
            ])
            ->all();
    }

    /** @param array<string, mixed> $metadata */
    private function record(string $event, InstrumentAsset $instrument, ?User $actor, string $path, array $metadata): void
    {
        app(AuditLogger::class)->record($event, $instrument, $actor, ['photo_path' => $path, ...$metadata]);
    }
}
