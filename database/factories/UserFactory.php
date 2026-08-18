<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Role::AGENT,
            'status' => UserStatus::ACTIVE,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => Role::ADMIN, 'status' => UserStatus::ACTIVE]);
    }

    public function staff(): static
    {
        return $this->state(fn () => ['role' => Role::STAFF, 'status' => UserStatus::ACTIVE]);
    }

    public function agent(UserStatus $status = UserStatus::ACTIVE): static
    {
        return $this->state(fn () => ['role' => Role::AGENT, 'status' => $status]);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['role' => Role::AGENT, 'status' => UserStatus::PENDING]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => UserStatus::SUSPENDED]);
    }

    /** Agents need a profile + settings row to behave like real signups. */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            UserSetting::firstOrCreate(['user_id' => $user->id]);

            if ($user->isAgent()) {
                AgentProfile::firstOrCreate(['user_id' => $user->id]);
            }
        });
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
