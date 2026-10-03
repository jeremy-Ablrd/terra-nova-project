<?php

namespace App\Services;

use RuntimeException;

/** Changement de statut refusé ; le message est lisible par l'agent. */
class TransitionRefusee extends RuntimeException {}
