<?php

namespace App\Services;

use RuntimeException;

/** Suppression de compte refusée (compte agent ou admin) ; le message est lisible par l'utilisateur. */
class SuppressionRefusee extends RuntimeException {}
