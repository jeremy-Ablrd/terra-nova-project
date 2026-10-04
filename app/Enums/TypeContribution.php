<?php

namespace App\Enums;

/** Les trois façons de contribuer : donner son avis sur un projet (ce n'est pas un vote), proposer une idée, commenter un service. */
enum TypeContribution: string
{
    case Avis = 'avis';
    case Idee = 'idee';
    case Commentaire = 'commentaire';

    public function label(): string
    {
        return match ($this) {
            self::Avis => __('Avis'),
            self::Idee => __('Idée'),
            self::Commentaire => __('Commentaire'),
        };
    }
}
