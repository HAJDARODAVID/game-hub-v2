<?php

namespace App\Games;

use RuntimeException;

/**
 * Thrown by an engine for a move that breaks the rules. The message is shown to the player.
 */
class InvalidMove extends RuntimeException
{
}
