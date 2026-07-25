<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Scaffold smoke test: PHPUnit is wired and assertions run.
 */
class ExampleTest extends TestCase
{
    /**
     * Confirms the unit-test harness can execute a trivial assertion.
     */
    public function test_that_true_is_true(): void
    {
        $this->assertTrue(true);
    }
}
