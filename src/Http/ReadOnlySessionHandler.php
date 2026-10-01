<?php

namespace Padmission\Tickets\Http;

use SessionHandlerInterface;

class ReadOnlySessionHandler implements SessionHandlerInterface
{
    public function __construct(public readonly SessionHandlerInterface $inner) {}

    public function open(string $path, string $name): bool
    {
        return $this->inner->open($path, $name);
    }

    public function close(): bool
    {
        return $this->inner->close();
    }

    public function read(string $id): string|false
    {
        return $this->inner->read($id);
    }

    public function write(string $id, string $data): bool
    {
        return true;
    }

    public function destroy(string $id): bool
    {
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return $this->inner->gc($max_lifetime);
    }
}
