<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TypeEvenementSecurite;
use App\Http\Controllers\Controller;
use App\Models\EvenementSecurite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Journal de sécurité : lecture seule, administrateur seul. Aucune donnée personnelle (e-mails masqués). */
class SecuriteController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', EvenementSecurite::class);

        // Filtre ?type=… : valeur inconnue (ou tableau) ignorée.
        $valeur = $request->query('type');
        $type = is_string($valeur) ? TypeEvenementSecurite::tryFrom($valeur) : null;

        // UNE requête groupée : total par type (compteurs du filtre) et nombre sur les dernières 24 heures.
        $stats = EvenementSecurite::query()->toBase()
            ->selectRaw('type, count(*) as total, sum(case when created_at >= ? then 1 else 0 end) as recents', [now()->subDay()->toDateTimeString()])
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $evenements = EvenementSecurite::query()
            ->when($type, fn ($q) => $q->where('type', $type->value))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.securite.index', [
            'evenements' => $evenements,
            'type' => $type,
            'totaux' => collect(TypeEvenementSecurite::cases())->mapWithKeys(fn ($t) => [$t->value => (int) ($stats[$t->value]->total ?? 0)]),
            'recents' => collect(TypeEvenementSecurite::cases())->mapWithKeys(fn ($t) => [$t->value => (int) ($stats[$t->value]->recents ?? 0)]),
            // Pour repérer tout de suite un problème de proxy : l'IP vue par la plateforme et la configuration des proxys.
            'ipDetectee' => $request->ip(),
            'proxysConfigures' => config('securite.trusted_proxies'),
            'enTeteRelais' => $request->headers->has('X-Forwarded-For'),
            'limiteIp' => (bool) config('securite.limite_ip'),
            'modeCsp' => config('securite.csp_mode'),
            // Les liens des documents téléchargés partent de APP_URL : « localhost » en production serait une erreur de configuration.
            'appUrl' => config('app.url'),
            'appUrlLocale' => app()->environment('production') && in_array(parse_url((string) config('app.url'), PHP_URL_HOST), ['localhost', '127.0.0.1', '::1'], true),
        ]);
    }
}
