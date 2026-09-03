<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Peran backoffice — layar `528:9745`.
 *
 * Memperluas model `spatie/laravel-permission`, bukan menggantinya: seluruh
 * mesin izinnya tetap milik paket itu. Yang ditambahkan di sini cuma empat
 * kolom yang diminta desain (`type`, `scope`, `summary`, `updated_by_id`)
 * berikut relasi penyuntingnya.
 *
 * Didaftarkan di `config/permission.php` (`models.role`) — tanpa baris itu,
 * `HasRoles` tetap membuat instance milik paket dan relasi di bawah tidak
 * pernah ada, tanpa satu pun galat.
 */
class Role extends SpatieRole
{
    public const TYPE_SYSTEM = 'system';

    public const TYPE_CUSTOM = 'custom';

    public const SCOPE_GLOBAL = 'global';

    public const SCOPE_FEDERATION = 'federation';

    /**
     * Kolom "Last Updated" (`528:9745`) menyebut nama.
     *
     * Tidak memakai trait `TracksEditor` seperti model isi: peran disunting
     * lewat satu controller dengan dua jalur simpan yang keduanya sudah
     * menuliskannya, sementara trait itu menempel di event `saving` dan akan
     * ikut menandai baris yang ditulis `AccessSeeder` — yang justru tidak
     * dilakukan siapa pun.
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    /** Peran bawaan yang lahir dari `App\Support\Access`, bukan dari layar. */
    public function isSystem(): bool
    {
        return ($this->type ?? self::TYPE_CUSTOM) === self::TYPE_SYSTEM;
    }
}
