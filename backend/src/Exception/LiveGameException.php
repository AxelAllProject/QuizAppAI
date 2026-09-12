<?php

namespace App\Exception;

/** Action impossible dans l'état actuel d'une partie en direct (trop tard, déjà répondu…). */
class LiveGameException extends \DomainException
{
}
