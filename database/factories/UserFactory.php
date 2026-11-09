<?php
/*
 * ============================================================
 * BUAT APA : Pabrik data user palsu untuk seeder dan tes.
 *            Contoh: User::factory()->admin()->create()
 * KENAPA   : Factory membuat data tanpa terkena aturan $fillable,
 *            jadi kolom 'role' bisa diisi dengan aman di sini.
 * ============================================================
 */

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => User::ROLE_CUSTOMER, // bawaan: customer
            'remember_token' => \Illuminate\Support\Str::random(10),
        ];
    }

    // State = variasi siap pakai
    public function admin(): static    { return $this->state(['role' => User::ROLE_ADMIN]); }
    public function operator(): static { return $this->state(['role' => User::ROLE_OPERATOR]); }
    public function customer(): static { return $this->state(['role' => User::ROLE_CUSTOMER]); }
}
