<?php

namespace Database\Factories;

use App\Models\IpWhitelistRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IpWhitelistRule>
 */
class IpWhitelistRuleFactory extends Factory
{
    protected $model = IpWhitelistRule::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Office',

            // Blok dokumentasi RFC 5737 (`192.0.2.0/24`, `198.51.100.0/24`,
            // `203.0.113.0/24`) — sama dengan yang dipakai desain. Alamat acak
            // dari `fake()->ipv4()` bisa jatuh di rentang milik orang lain, dan
            // data uji yang menyebut alamat nyata adalah cara termudah membuat
            // seseorang menyalinnya ke produksi.
            'ip_range' => '203.0.113.'.fake()->numberBetween(1, 254),

            'scope' => IpWhitelistRule::SCOPE_ALL,
            'role_id' => null,
            'user_id' => null,
            'validity' => IpWhitelistRule::VALIDITY_PERMANENT,
            'expires_at' => null,
            'notes' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function temporary(?string $expiresAt = null): static
    {
        return $this->state(fn () => [
            'validity' => IpWhitelistRule::VALIDITY_TEMPORARY,
            'expires_at' => $expiresAt ?? now()->addDays(7),
        ]);
    }

    /** Sudah lewat tanggalnya — aktif di kolom Status, tapi tidak ditegakkan. */
    public function expired(): static
    {
        return $this->state(fn () => [
            'validity' => IpWhitelistRule::VALIDITY_TEMPORARY,
            'expires_at' => now()->subDay(),
        ]);
    }

    public function forRole(int $roleId): static
    {
        return $this->state(fn () => [
            'scope' => IpWhitelistRule::SCOPE_ROLE,
            'role_id' => $roleId,
            'user_id' => null,
        ]);
    }

    public function forUser(int $userId): static
    {
        return $this->state(fn () => [
            'scope' => IpWhitelistRule::SCOPE_USER,
            'user_id' => $userId,
            'role_id' => null,
        ]);
    }
}
