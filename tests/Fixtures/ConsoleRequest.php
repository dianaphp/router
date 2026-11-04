<?php

namespace Diana\Router\Tests\Fixtures;

use Diana\Contracts\IO\ConsoleRequest as ConsoleRequestContract;

class ConsoleRequest implements ConsoleRequestContract
{
    public function __construct(private string $command, private array $arguments = [])
    {
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getDefaultStatusCode(): int
    {
        return 0;
    }
}
