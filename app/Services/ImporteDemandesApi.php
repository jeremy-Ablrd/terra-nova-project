<?php

namespace App\Services;

use App\Enums\TypeDemande;
use App\Models\ApiRequest;
use App\Models\Demande;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Importe dans `demandes` les demandes de l'API dont requester_type vaut exactement « Citoyen ».
 * Lit la table api_requests (aucun appel HTTP). Idempotent : une demande déjà importée n'est jamais modifiée
 * (son statut local ne doit jamais être écrasé). Chaque demande passe par save() pour que l'événement `created`
 * génère la référence. À appeler sous le verrou de synchronisation (NovaTerraApi::withLock).
 */
class ImporteDemandesApi
{
    public const TYPE_CITOYEN = 'Citoyen';

    private const OBJET_LIMITE = 80;

    /** @return int nombre de demandes créées */
    public function import(): int
    {
        // Le « = » SQL ignore la casse sur MySQL : on revérifie en PHP que la valeur est exactement « Citoyen ».
        $lignes = ApiRequest::where('requester_type', self::TYPE_CITOYEN)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (ApiRequest $ligne) => $ligne->requester_type === self::TYPE_CITOYEN);

        if ($lignes->isEmpty()) {
            return 0;
        }

        $dejaImportees = Demande::whereIn('request_code', $lignes->pluck('request_code'))
            ->pluck('request_code')
            ->all();

        return DB::transaction(function () use ($lignes, $dejaImportees) {
            $creees = 0;

            foreach ($lignes as $ligne) {
                if (in_array($ligne->request_code, $dejaImportees, true)) {
                    continue; // jamais de mise à jour d'une demande déjà importée
                }

                if (blank($ligne->message_public)) {
                    Log::warning("Import des demandes : {$ligne->request_code} ignorée (message vide).");

                    continue;
                }

                $demande = new Demande;
                $demande->objet = Str::limit($ligne->message_public, self::OBJET_LIMITE);
                $demande->message = $ligne->message_public;
                $demande->service_id = null;
                $demande->user_id = null;
                $demande->demandeur_nom = $ligne->requester_name;
                $demande->request_code = $ligne->request_code;
                $demande->type = TypeDemande::Citoyen;
                $demande->save(); // statut « nouvelle » par défaut ; la référence est générée par l'événement `created`

                $creees++;
            }

            return $creees;
        });
    }
}
