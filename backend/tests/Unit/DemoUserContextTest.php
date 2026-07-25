<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\DemoUserContext;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Request-scoped demo user holder used by middleware and ownership checks.
 */
class DemoUserContextTest extends TestCase
{
    #[TestDox('Resolves the selected demo user after set')]
    public function test_resolves_selected_demo_user_after_set(): void
    {
        $context = new DemoUserContext;
        $user = new User;
        $user->id = 42;

        $this->assertFalse($context->check());

        $context->set($user);

        $this->assertTrue($context->check());
        $this->assertSame($user, $context->user());
        $this->assertSame(42, $context->id());
    }

    #[TestDox('Clearing removes the resolved demo user')]
    public function test_clear_removes_resolved_demo_user(): void
    {
        $context = new DemoUserContext;
        $user = new User;
        $user->id = 7;
        $context->set($user);

        $context->clear();

        $this->assertFalse($context->check());
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No demo user has been resolved for this request.');
        $context->user();
    }

    #[TestDox('Reading user before resolution throws')]
    public function test_reading_user_before_resolution_throws(): void
    {
        $context = new DemoUserContext;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No demo user has been resolved for this request.');
        $context->id();
    }
}
