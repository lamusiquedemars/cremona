<?php

namespace App\Services;

use App\Models\InstrumentAsset;
use App\Models\InstrumentCategory;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class InstrumentRentalPricing
{
    public function matchingCategories(string $family, string $size, int $tierId, ?int $exceptCategoryId = null): Collection
    {
        return InstrumentCategory::query()
            ->where('family', $family)
            ->where('rental_tier_id', $tierId)
            ->where('is_active', true)
            ->when($exceptCategoryId, fn ($query) => $query->whereKeyNot($exceptCategoryId))
            ->get()
            ->filter(fn (InstrumentCategory $category): bool => in_array($size, $category->eligible_sizes ?? [], true))
            ->values();
    }

    public function applyToInstrument(InstrumentAsset $instrument, bool $requireProfile = false): void
    {
        if (! $instrument->available_for_rental) {
            return;
        }

        if (blank($instrument->family) || blank($instrument->rental_size) || blank($instrument->rental_tier_id)) {
            if (! $requireProfile) {
                return;
            }

            throw ValidationException::withMessages([
                'rental_size' => 'Une famille, une taille et une gamme sont nécessaires pour proposer un instrument à la location.',
            ]);
        }

        $matches = $this->matchingCategories($instrument->family, $instrument->rental_size, (int) $instrument->rental_tier_id);

        if ($matches->count() > 1) {
            if (! $requireProfile) {
                return;
            }

            throw ValidationException::withMessages([
                'rental_size' => 'Plusieurs grilles de location correspondent à cet instrument. Corrigez les grilles avant de continuer.',
            ]);
        }

        if ($matches->isEmpty()) {
            if (! $requireProfile) {
                return;
            }

            throw ValidationException::withMessages([
                'rental_size' => 'Aucune grille de location active ne correspond à cette famille, cette taille et cette gamme.',
            ]);
        }

        $instrument->instrument_category_id = $matches->first()?->id;
    }

    /**
     * Retourne la grille unique applicable, sans modifier la fiche instrument.
     *
     * Cette lecture est aussi utilisée par les dossiers créés avant qu'une grille
     * ne soit configurée : ils restent ainsi lisibles avant leur prochaine sauvegarde.
     */
    public function resolvedCategoryForInstrument(InstrumentAsset $instrument): ?InstrumentCategory
    {
        if (! $instrument->available_for_rental) {
            return null;
        }

        if (blank($instrument->family) || blank($instrument->rental_size) || blank($instrument->rental_tier_id)) {
            return null;
        }

        $matches = $this->matchingCategories($instrument->family, $instrument->rental_size, (int) $instrument->rental_tier_id);

        return $matches->count() === 1 ? $matches->first() : $instrument->category;
    }

    /**
     * Une modification de grille doit également raccrocher les instruments déjà
     * présents dans le parc : l'utilisateur ne doit pas avoir à rouvrir chaque fiche.
     */
    public function synchronizeInstrumentsForCategory(InstrumentCategory $category): void
    {
        InstrumentAsset::withoutGlobalScopes()
            ->where('organization_id', $category->organization_id)
            ->where('family', $category->family)
            ->where('rental_tier_id', $category->rental_tier_id)
            ->where('available_for_rental', true)
            ->eachById(function (InstrumentAsset $instrument): void {
                $this->applyToInstrument($instrument);

                if ($instrument->isDirty('instrument_category_id')) {
                    $instrument->save();
                }
            });
    }

    public function assertCategoryDoesNotOverlap(InstrumentCategory $category): void
    {
        if (! ($category->is_active ?? true) || blank($category->family) || blank($category->rental_tier_id)) {
            return;
        }

        $sizes = array_filter((array) $category->eligible_sizes);
        if ($sizes === []) {
            return;
        }

        if (! $category->rentalTier()->exists()) {
            throw ValidationException::withMessages([
                'rental_tier_id' => 'La gamme sélectionnée doit appartenir à l’organisation active.',
            ]);
        }

        $conflicts = InstrumentCategory::query()
            ->where('family', $category->family)
            ->where('rental_tier_id', $category->rental_tier_id)
            ->where('is_active', true)
            ->when($category->exists, fn ($query) => $query->whereKeyNot($category->getKey()))
            ->get()
            ->filter(fn (InstrumentCategory $other): bool => array_intersect($sizes, $other->eligible_sizes ?? []) !== [])
            ->pluck('name');

        if ($conflicts->isNotEmpty()) {
            throw ValidationException::withMessages([
                'eligible_sizes' => 'Cette grille recouvre déjà : '.$conflicts->join(', ').'. Une combinaison famille, taille et gamme ne peut avoir qu’un seul tarif actif.',
            ]);
        }
    }
}
