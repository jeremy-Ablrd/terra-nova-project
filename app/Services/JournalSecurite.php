<?php

namespace App\Services;

use App\Enums\TypeEvenementSecurite;
use App\Models\EvenementSecurite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Journal de sécurité (F37, F54, F70). Jamais de mot de passe, de contenu de message ni de donnée d'habitant :
 * l'adresse e-mail n'est enregistrée que masquée (j***@d***.fr). Écriture par save(), un événement à la fois.
 */
class JournalSecurite
{
    /** Au plus 10 accès refusés enregistrés par utilisateur et par minute, pour ne pas inonder la table. */
    public const PLAFOND_ACCES_REFUSES = 10;

    /** « camille@example.fr » devient « c***@e***.fr » ; sans forme d'adresse reconnaissable, « *** ». */
    public static function masquerEmail(?string $email): ?string
    {
        if ($email === null || trim($email) === '') {
            return null;
        }

        [$local, $domaine] = array_pad(explode('@', Str::lower(trim($email)), 2), 2, '');
        if ($local === '' || $domaine === '') {
            return '***';
        }

        $labels = explode('.', $domaine);
        $fin = count($labels) > 1 ? '.'.array_pop($labels) : '';

        return mb_substr($local, 0, 1).'***@'.mb_substr(implode('.', $labels), 0, 1).'***'.$fin;
    }

    public function enregistrer(
        TypeEvenementSecurite $type,
        ?User $user = null,
        ?string $email = null,
        ?string $detail = null,
        ?int $appareilId = null,
        ?Request $request = null,
    ): EvenementSecurite {
        $request ??= request();

        $evenement = new EvenementSecurite;
        $evenement->type = $type;
        $evenement->user_id = $user?->id;
        $evenement->email_masque = self::masquerEmail($email);
        $evenement->ip = (string) ($request->ip() ?? '');
        $evenement->route = $request->route()?->getName() ?? $request->path();
        $evenement->detail = $detail === null ? null : Str::limit($detail, 250, '…');
        $evenement->appareil_id = $appareilId;
        $evenement->created_at = now();
        $evenement->save();

        return $evenement;
    }

    /** Accès refusé (403) sur /agent/* ou /admin/* : route, rôle et compte, avec un plafond par utilisateur et par minute. */
    public function accesRefuse(Request $request): void
    {
        $user = $request->user();
        if ($user === null) {
            return;
        }

        $cle = 'securite:acces-refuse:'.$user->id;
        if (RateLimiter::tooManyAttempts($cle, self::PLAFOND_ACCES_REFUSES)) {
            return;
        }
        RateLimiter::hit($cle, 60);

        $this->enregistrer(TypeEvenementSecurite::AccesRefuse, $user, null, 'rôle : '.$user->role->value, null, $request);
    }
}
