<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Levée quand on tente d'écrire une donnée sans structure courante,
 * ou dans une structure qui n'est pas la structure courante.
 */
class StructureNonAutoriseeException extends RuntimeException {}
