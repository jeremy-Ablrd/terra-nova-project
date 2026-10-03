<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\Statut;
use App\Models\Service;
use App\Models\User;
use App\Services\TransitionDemande;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /** @param  array{email: string, env: string}  $account */
    private function passwordFor(array $account): string
    {
        $password = env($account['env']);

        if (blank($password)) {
            $password = Str::password(16, symbols: false);
            $this->command?->warn("{$account['env']} est vide : mot de passe généré pour {$account['email']} → {$password}");
        }

        return $password;
    }

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ServiceSeeder::class);
        $this->call(UrgenceSeeder::class);

        // Comptes de démonstration, relançable sans doublon. Les mots de passe ne sont jamais dans le code :
        // ils viennent du .env (SEED_*_PASSWORD) ; si la variable est vide, un mot de passe aléatoire est généré et affiché.
        $accounts = [
            ['email' => 'citoyen@novaterra.test', 'name' => 'Camille Citoyen', 'role' => Role::Citoyen, 'env' => 'SEED_CITOYEN_PASSWORD'],
            ['email' => 'agent@novaterra.test', 'name' => 'Alex Agent', 'role' => Role::Agent, 'env' => 'SEED_AGENT_PASSWORD'],
            ['email' => 'admin@novaterra.test', 'name' => 'Sacha Admin', 'role' => Role::Admin, 'env' => 'SEED_ADMIN_PASSWORD'],
        ];

        foreach ($accounts as $account) {
            User::firstOrNew(['email' => $account['email']])->forceFill([
                'name' => $account['name'],
                'password' => $this->passwordFor($account),
                'role' => $account['role'],
                'email_verified_at' => now(),
            ])->save();
        }

        // Alertes de démonstration (dates relatives à maintenant, jamais de date fixe).
        $this->call(AlerteSeeder::class);

        // Demandes de démonstration pour le citoyen (une seule fois).
        $citoyen = User::where('email', 'citoyen@novaterra.test')->first();
        $agent = User::where('email', 'agent@novaterra.test')->first();
        if ($citoyen->demandes()->doesntExist()) {
            $demos = [
                ['voirie-proprete', 'Lampadaire en panne', 'Le lampadaire du 12 rue des Lilas ne fonctionne plus depuis une semaine.', Statut::Nouvelle],
                ['transports', 'Arrêt de bus déplacé', 'Pouvez-vous m\'indiquer le nouvel emplacement de l\'arrêt de la ligne 4 ?', Statut::EnCours],
                ['culture-loisirs', 'Inscription à la bibliothèque', 'Merci de confirmer mon inscription pour la rentrée.', Statut::Traitee],
            ];

            // Les statuts avancent par le service de transition (étapes d'historique et journal compris).
            $transition = app(TransitionDemande::class);

            foreach ($demos as [$slug, $objet, $message, $statut]) {
                $demande = $citoyen->demandes()->create([
                    'objet' => $objet,
                    'message' => $message,
                    'service_id' => Service::where('slug', $slug)->value('id'),
                ])->refresh();

                while ($demande->statut !== $statut) {
                    $demande = $transition->passer($demande, $demande->statut, $agent);
                }
            }
        }
    }
}
