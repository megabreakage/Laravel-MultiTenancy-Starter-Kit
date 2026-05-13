<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Central\SuperAdmin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<SuperAdmin>
 */
class SuperAdminFactory extends Factory
{
    protected $model = SuperAdmin::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional(0.3)->firstName(),
            'last_name' => fake()->lastName(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'country_code' => '+254',
            'phone' => fake()->optional()->phoneNumber(),
            'password' => Hash::make('password'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }
}
