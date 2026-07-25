<?php

namespace App\Support;

use App\Models\User;
use RuntimeException;

final class DemoUserContext
{
    private ?User $user = null;

    public function set(User $user): void
    {
        $this->user = $user;
    }

    public function clear(): void
    {
        $this->user = null;
    }

    public function check(): bool
    {
        return $this->user !== null;
    }

    public function user(): User
    {
        if ($this->user === null) {
            throw new RuntimeException('No demo user has been resolved for this request.');
        }

        return $this->user;
    }

    public function id(): int
    {
        return $this->user()->id;
    }
}
