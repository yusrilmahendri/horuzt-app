<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BukuTamu;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BukuTamuFactory extends Factory
{
    protected $model = BukuTamu::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nama' => $this->faker->name(),
            'email' => $this->faker->optional(0.7)->safeEmail(),
            'telepon' => $this->faker->optional(0.6)->phoneNumber(),
            'ucapan' => $this->faker->optional(0.8)->paragraph(2),
            'status_kehadiran' => $this->faker->randomElement(['hadir', 'tidak_hadir', 'ragu']),
            'jumlah_tamu' => $this->faker->numberBetween(1, 5),
            'is_approved' => $this->faker->boolean(90),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
        ];
    }

    public function hadir(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_kehadiran' => 'hadir',
        ]);
    }

    public function tidakHadir(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_kehadiran' => 'tidak_hadir',
        ]);
    }

    public function ragu(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_kehadiran' => 'ragu',
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_approved' => true,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_approved' => false,
        ]);
    }
}
