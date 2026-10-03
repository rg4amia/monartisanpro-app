<?php

namespace App\Exceptions;

use DomainException;

/**
 * Changement d'état de mission refusé : transition absente du graphe ou
 * garde métier non satisfaite. Le message est destiné à l'utilisateur.
 */
class MissionTransitionException extends DomainException {}
