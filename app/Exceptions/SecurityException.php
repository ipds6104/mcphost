<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class SecurityException extends Exception
{
    /**
     * Create a new exception instance.
     */
    public function __construct(string $message = 'Upaya manipulasi prompt terdeteksi. Permintaan Anda ditolak.', int $code = 403, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
