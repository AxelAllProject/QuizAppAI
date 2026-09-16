<?php

namespace App\Shared\Domain\Exception;

/**
 * Une écriture repose sur une version périmée d'un agrégat : quelqu'un l'a modifié entre
 * la lecture et l'enregistrement (ex. deux générations IA lancées au même instant avec
 * la dernière génération d'une clé). L'opération est annulée plutôt que d'écraser l'autre.
 */
class ConcurrentModificationException extends \RuntimeException
{
}
