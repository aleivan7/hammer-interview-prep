<?php

namespace Database\Factories;

use App\Enums\PersonaType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->name();

        return [
            'name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => null,
            'remember_token' => Str::random(10),
            'persona_type' => PersonaType::Average,
            'persona_label' => PersonaType::Average->label(),
            'description' => 'Synthetic demo persona for ClearSpend interviews.',
            'member_since' => '2026-01-01',
            'avatar_initials' => $this->initialsFromName($name),
        ];
    }

    public function reckless(): static
    {
        return $this->state(fn () => [
            'name' => 'Alex Rivera',
            'email' => 'alex.rivera@clearspend.demo',
            'persona_type' => PersonaType::Reckless,
            'persona_label' => PersonaType::Reckless->label(),
            'description' => 'High lifestyle spending, thin cash buffers, and rising credit-card balances.',
            'member_since' => '2025-11-12',
            'avatar_initials' => 'AR',
        ]);
    }

    public function average(): static
    {
        return $this->state(fn () => [
            'name' => 'Jordan Lee',
            'email' => 'jordan.lee@clearspend.demo',
            'persona_type' => PersonaType::Average,
            'persona_label' => PersonaType::Average->label(),
            'description' => 'Steady paycheck, rent and utilities, and a mostly healthy 50/30/20 plan.',
            'member_since' => '2026-01-01',
            'avatar_initials' => 'JL',
        ]);
    }

    public function highNetWorth(): static
    {
        return $this->state(fn () => [
            'name' => 'Morgan Chen',
            'email' => 'morgan.chen@clearspend.demo',
            'persona_type' => PersonaType::HighNetWorth,
            'persona_label' => PersonaType::HighNetWorth->label(),
            'description' => 'High income with brokerage, retirement, and travel spending that still fits a strong savings plan.',
            'member_since' => '2024-03-18',
            'avatar_initials' => 'MC',
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withPassword(): static
    {
        return $this->state(fn () => [
            'password' => static::$password ??= Hash::make('password'),
        ]);
    }

    private function initialsFromName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $initials = collect($parts)
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : 'CS';
    }
}
