<?php

namespace App\Shared\Domain\Exception;

/** Une écriture violerait une règle d'unicité (ex. deux réponses envoyées au même instant). */
class DuplicateEntryException extends \RuntimeException
{
}
