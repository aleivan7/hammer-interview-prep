<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    protected function createDemoUser(array $attributes = []): User
    {
        return User::factory()->average()->create($attributes);
    }

    protected function withDemoUser(?User $user = null): User
    {
        $user ??= $this->createDemoUser();
        $this->withHeader('X-Demo-User', (string) $user->id);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    protected function getJsonAs(User $user, string $uri, array $headers = []): TestResponse
    {
        return $this->withHeader('X-Demo-User', (string) $user->id)->getJson($uri, $headers);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $headers
     */
    protected function postJsonAs(User $user, string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->withHeader('X-Demo-User', (string) $user->id)->postJson($uri, $data, $headers);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $headers
     */
    protected function patchJsonAs(User $user, string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->withHeader('X-Demo-User', (string) $user->id)->patchJson($uri, $data, $headers);
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    protected function deleteJsonAs(User $user, string $uri, array $headers = []): TestResponse
    {
        return $this->withHeader('X-Demo-User', (string) $user->id)->deleteJson($uri, [], $headers);
    }
}
