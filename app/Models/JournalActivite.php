<?php

namespace App\Models;

use App\Enums\ActionJournal;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Entrée du journal d'activité : qui (acteur), quoi (action), sur quel objet, quand. Créée uniquement par
 * App\Services\Journal::enregistrer(). Jamais modifiée, jamais supprimée : update() et delete() lèvent une exception.
 */
class JournalActivite extends Model
{
    protected $table = 'journal_activites';

    /** Pas de updated_at : seule la date de création existe (posée à la création, heure locale de l'application). */
    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (JournalActivite $entree) {
            $entree->created_at ??= now();
        });
        static::updating(fn () => self::refuser());
        static::deleting(fn () => self::refuser());
    }

    private static function refuser(): never
    {
        throw new LogicException('Le journal d\'activité est en lecture seule : aucune mise à jour ni suppression.');
    }

    protected function casts(): array
    {
        return [
            'acteur_role' => Role::class,
            'action' => ActionJournal::class,
            'created_at' => 'datetime',
        ];
    }

    /** Type d'objet en français pour l'affichage. */
    public function typeObjetLibelle(): string
    {
        return match ($this->objet_type) {
            'demande' => __('Demande'),
            'service' => __('Service'),
            'alerte' => __('Alerte'),
            'compte' => __('Compte'),
            'synchronisation' => __('Synchronisation'),
            default => ucfirst($this->objet_type),
        };
    }

    public function newEloquentBuilder($query): JournalActiviteBuilder
    {
        return new JournalActiviteBuilder($query);
    }

    /** Une entrée existante ne s'enregistre plus : seule la création est permise. */
    public function save(array $options = [])
    {
        if ($this->exists) {
            self::refuser();
        }

        return parent::save($options);
    }

    public function update(array $attributes = [], array $options = [])
    {
        self::refuser();
    }

    public function delete()
    {
        self::refuser();
    }

    public function forceDelete()
    {
        self::refuser();
    }

    public function deleteQuietly()
    {
        self::refuser();
    }
}
