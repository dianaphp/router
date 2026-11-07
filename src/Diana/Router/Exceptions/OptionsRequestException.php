<?php

namespace Diana\Router\Exceptions;

use Exception;

class OptionsRequestException extends Exception
{
    /** @param list<string> $allowedMethods */
    public function __construct(private array $allowedMethods)
    {
        parent::__construct('Allowed methods: ' . implode(', ', $allowedMethods), 204);
    }

    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
