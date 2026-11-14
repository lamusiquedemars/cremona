<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Conserve une provenance unique dans l'historique après le retrait du
     * produit ancien. Les clés d'idempotence restent volontairement intactes :
     * elles identifient des événements déjà reçus et ne doivent jamais être
     * réécrites.
     */
    public function up(): void
    {
        DB::table('incoming_requests')
            ->where('source', 'maracuja-cms')
            ->update(['source' => 'maracuja-site']);
    }

    public function down(): void
    {
        // Migration de données irréversible : ne pas reclasser des demandes
        // reçues après la bascule comme provenant de l'ancien produit.
    }
};
