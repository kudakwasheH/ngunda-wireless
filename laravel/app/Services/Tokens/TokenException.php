<?php

namespace App\Services\Tokens;

use RuntimeException;

/** Redemption refusal. $reason is a stable machine code; getMessage() is safe to show customers. */
class TokenException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function invalid(): self
    {
        // Deliberately identical for unknown / malformed codes so codes cannot be probed.
        return new self('invalid', 'This token is not valid.');
    }
}
