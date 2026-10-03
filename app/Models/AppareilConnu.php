<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un navigateur déjà utilisé pour se connecter à un compte (F54). */
class AppareilConnu extends Model
{
    protected $table = 'appareils_connus';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['premiere_vue_at' => 'datetime', 'derniere_vue_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
