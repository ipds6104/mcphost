<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class OffTopicQueryException extends Exception
{
    /**
     * Create a new exception instance.
     */
    public function __construct(string $message = 'Permintaan di luar konteks analitis statistik.', int $code = 400, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
