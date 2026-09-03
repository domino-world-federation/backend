<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Pengaturan situs sebagai pasangan kunci-nilai.
 *
 * SENGAJA tanpa `RecordsActivity`. Dua alasan, keduanya nyata:
 *   1. Kunci primernya string, sementara `activity_log.subject_id` bertipe
 *      bigint — mencatatnya lewat trait melempar galat Postgres saat menyimpan.
 *   2. Satu kali menekan Simpan menulis sembilan baris, jadi trait akan
 *      menghasilkan sembilan entri log untuk satu tindakan.
 *
 * `ContactSettingController` yang mencatatnya: satu entri berisi seluruh
 * perubahannya, yang justru itu yang dicari saat mengaudit.
 *
 * Kunci primernya string, jadi `$incrementing` harus dimatikan — kalau tidak,
 * Eloquent memperlakukan `key` sebagai integer dan `find('primary_email')`
 * mengembalikan baris yang salah tanpa galat.
 */
#[Fillable(['key', 'group', 'value'])]
class SiteSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    /** Kontak, tautan sosial, alamat — dibaca footer dan halaman Contact. */
    public const GROUP_CONTACT = 'contact';

    /** Naskah halaman depan yang tidak dimiliki modul mana pun. */
    public const GROUP_HOME = 'home';

    /**
     * Satu kelompok pengaturan sebagai peta kunci => nilai.
     *
     * Sengaja BUKAN bernama `all()`: itu method statis milik Eloquent dengan
     * tipe kembalian Collection, dan menimpanya dengan array membuat kode yang
     * memanggil `SiteSetting::all()` secara wajar rusak tanpa peringatan.
     *
     * Kelompoknya WAJIB disebut. Bawaan "semua" akan membuat endpoint yang
     * lupa menyebutnya mengirim naskah halaman depan ke footer — persis
     * kesalahan yang kolom `group` ditambahkan untuk mencegahnya.
     *
     * @return array<string, string|null>
     */
    public static function map(string $group): array
    {
        return static::query()->where('group', $group)->pluck('value', 'key')->all();
    }

    /** @param  array<string, string|null>  $values */
    public static function putMany(array $values, string $group): void
    {
        foreach ($values as $key => $value) {
            static::query()->updateOrCreate(['key' => $key], ['group' => $group, 'value' => $value]);
        }
    }
}
