<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Accounts API: listing scoped to the selected demo user.
 */
class AccountApiTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Account listing belongs only to the selected user')]
    public function test_account_listing_belongs_only_to_selected_user(): void
    {
        $selected = User::factory()->average()->create();
        $other = User::factory()->reckless()->create();

        Account::factory()->for($selected)->create([
            'name' => 'Jordan Checking',
            'sort_order' => 1,
        ]);
        Account::factory()->for($other)->create([
            'name' => 'Alex Checking',
            'sort_order' => 1,
        ]);

        $this->getJsonAs($selected, '/api/accounts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Jordan Checking');
    }
}
