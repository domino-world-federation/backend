<?php

namespace Database\Factories;

use App\Models\User;
use App\Support\Access;
use Database\Seeders\AccessSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Pengguna dengan peran tertentu.
     *
     * Peran dan izinnya diseed lebih dulu kalau belum ada — tabelnya kosong di
     * awal tiap tes `RefreshDatabase`, dan `assignRole` pada peran yang tidak
     * ada melempar galat yang tidak menjelaskan apa pun.
     */
    public function withRole(string $role): static
    {
        return $this->afterCreating(function (User $user) use ($role) {
            if (Role::query()->doesntExist()) {
                (new AccessSeeder)->run();
            }

            $user->syncRoles([$role]);
        });
    }

    /**
     * Aktor yang boleh melakukan apa pun.
     *
     * Dipakai tes modul yang memang tidak sedang menguji otorisasi. Sengaja
     * EKSPLISIT dan bukan bawaan `definition()`: pengguna bawaan tanpa peran
     * berarti tes yang lupa memikirkan izin akan gagal keras, bukan lolos
     * diam-diam karena kebetulan aktornya serba boleh.
     */
    public function superAdmin(): static
    {
        return $this->withRole(Access::SUPER_ADMIN);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
