<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'google_id' => 'fake_' . fake()->unique()->uuid(),
            'name'      => fake()->name(),
            'email'     => fake()->unique()->safeEmail(),
            'avatar'    => null,
            'role'      => 'staff',
        ];
    }
}
