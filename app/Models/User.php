<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function demandes(): HasMany
    {
        return $this->hasMany(Demande::class);
    }

    public function isCitoyen(): bool
    {
        return $this->role === Role::Citoyen;
    }

    public function isAgent(): bool
    {
        return $this->role === Role::Agent;
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /** Page d'accueil de l'utilisateur selon son rôle (chemin relatif). */
    public function homeUrl(): string
    {
        return route(match ($this->role) {
            Role::Admin => 'admin.index',
            Role::Agent => 'agent.index',
            default => 'dashboard',
        }, absolute: false);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'preferences' => 'array',
            'donnees_api_vues_at' => 'datetime',
        ];
    }
}
