<?php

namespace Tests\Feature;

use App\Services\DemoPersonaDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Demo persona listing and protected-endpoint header requirements.
 */
class DemoUserApiTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Persona-list endpoint returns exactly three demo users')]
    public function test_persona_list_returns_exactly_three_demo_users(): void
    {
        app(DemoPersonaDataService::class)->seedAllPersonas(Carbon::parse('2026-07-25'));

        $response = $this->getJson('/api/demo-users');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'name',
                        'email',
                        'persona_type',
                        'persona_label',
                        'description',
                        'avatar_initials',
                        'monthly_income_cents',
                        'monthly_income',
                        'account_count',
                        'financial_status_label',
                    ],
                ],
            ]);

        $labels = collect($response->json('data'))->pluck('persona_label')->all();
        $this->assertContains('Reckless Spender', $labels);
        $this->assertContains('Average Spender', $labels);
        $this->assertContains('High-Net-Worth Individual', $labels);
    }

    #[TestDox('Protected endpoints reject a missing demo-user header')]
    public function test_protected_endpoints_reject_missing_demo_user_header(): void
    {
        $this->getJson('/api/dashboard')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'demo_user_required');

        $this->getJson('/api/accounts')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'demo_user_required');
    }

    #[TestDox('Protected endpoints reject an invalid demo-user header')]
    public function test_protected_endpoints_reject_invalid_demo_user_header(): void
    {
        $this->withHeader('X-Demo-User', '999999')
            ->getJson('/api/dashboard')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'demo_user_invalid');

        $this->withHeader('X-Demo-User', 'abc')
            ->getJson('/api/profile')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'demo_user_invalid');
    }

    #[TestDox('Persona-list endpoint remains accessible without a demo-user header')]
    public function test_persona_list_is_public(): void
    {
        app(DemoPersonaDataService::class)->seedAllPersonas(Carbon::parse('2026-07-25'));

        $this->getJson('/api/demo-users')->assertOk();
    }
}
