<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * An AI provider could not answer. The message is safe to show to admins.
 * When the provider did answer, but unusably, $usage holds the tokens it still charged.
 */
class AiException extends RuntimeException
{
    public function __construct(string $message, public readonly ?AiCompletion $usage = null)
    {
        parent::__construct($message);
    }
}
