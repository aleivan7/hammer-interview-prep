<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Scaffold smoke test: Laravel HTTP kernel responds successfully.
 */
class ExampleTest extends TestCase
{
    /**
     * Confirms GET / returns HTTP 200 from the default application route.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
