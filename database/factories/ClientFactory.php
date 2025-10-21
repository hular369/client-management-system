<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    protected $model = \App\Models\Client::class;

    public function definition()
    {
        return [
            'company_name' => $this->faker->company(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone_number' => $this->faker->phoneNumber(),
            'is_duplicate' => false,
            'duplicate_group_hash' => null,
        ];
    }

    public function duplicate()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_duplicate' => true,
                'duplicate_group_hash' => md5($attributes['company_name'] . $attributes['email'] . $attributes['phone_number']),
            ];
        });
    }
}