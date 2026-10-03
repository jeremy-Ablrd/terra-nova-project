<?php

namespace App\Enums;

enum Role: string
{
    case Citoyen = 'citoyen';
    case Agent = 'agent';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Citoyen => 'Citoyen',
            self::Agent => 'Agent',
            self::Admin => 'Administrateur',
        };
    }
}
