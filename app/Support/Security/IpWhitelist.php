<?php

namespace App\Support\Security;

use App\Models\IpWhitelistRule;
use App\Models\User;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Kebijakan daftar IP backoffice — Figma `527:7038`.
 *
 * Seluruh keputusan "boleh atau tidak" ada DI SINI, bukan di middleware.
 * Alasannya bukan kerapian: penjaga anti-kunci-diri-sendiri di controller
 * (§`wouldLockOut`) harus menjawab pertanyaan yang sama persis dengan yang
 * dijawab middleware, tapi terhadap keadaan tabel SESUDAH perubahan yang belum
 * disimpan. Dua salinan logika akan berpisah, dan yang terjadi bukan galat —
 * melainkan tombol Save yang meloloskan perubahan yang mengunci pemakainya.
 */
final class IpWhitelist
{
    /**
     * Apakah `$ip` cocok dengan satu pola aturan.
     *
     * `IpUtils` bawaan Symfony (sudah ikut Laravel) menangani IPv4, IPv6, dan
     * CIDR sekaligus — termasuk hal yang mudah salah kalau ditulis tangan,
     * seperti membandingkan alamat dari keluarga yang berbeda.
     */
    public static function matches(string $pattern, string $ip): bool
    {
        return IpUtils::checkIp($ip, trim($pattern));
    }

    /**
     * Apakah dua pola beririsan — dipakai validasi "duplicate or overlapping
     * rules within the same access scope are blocked" (`527:8163`).
     *
     * Dua blok CIDR yang sekeluarga hanya punya dua kemungkinan: terpisah
     * sama sekali, atau yang satu memuat yang lain. (Kalau ada satu alamat yang
     * cocok dengan kedua prefix, prefix yang lebih pendek pasti awalan dari
     * yang lebih panjang.) Jadi memeriksa "alamat A ada di dalam B, atau alamat
     * B ada di dalam A" sudah lengkap — tidak perlu aritmetika rentang.
     *
     * Bagian alamat dipakai apa adanya sebagai penyelidik, termasuk kalau
     * seseorang menulis `203.0.113.5/24` dengan bit host menyala: alamat itu
     * tetap anggota rentangnya sendiri, jadi hasilnya tetap benar.
     */
    public static function overlaps(string $a, string $b): bool
    {
        $ipA = self::addressPart($a);
        $ipB = self::addressPart($b);

        return IpUtils::checkIp($ipA, trim($b)) || IpUtils::checkIp($ipB, trim($a));
    }

    /**
     * Aturan yang MENYASAR pengguna ini — lingkupnya "semua admin", salah satu
     * perannya, atau dirinya sendiri.
     *
     * @return Collection<int, IpWhitelistRule>
     */
    public static function rulesTargeting(User $user): Collection
    {
        $roleIds = $user->roles->pluck('id');

        return IpWhitelistRule::query()
            ->enforceable()
            ->where(fn ($q) => $q
                ->where('scope', IpWhitelistRule::SCOPE_ALL)
                ->orWhere(fn ($r) => $r
                    ->where('scope', IpWhitelistRule::SCOPE_ROLE)
                    ->whereIn('role_id', $roleIds))
                ->orWhere(fn ($u) => $u
                    ->where('scope', IpWhitelistRule::SCOPE_USER)
                    ->where('user_id', $user->id)))
            ->get();
    }

    /**
     * Boleh masuk atau tidak, dihitung dari satu himpunan aturan.
     *
     * ── Keputusan: tabel KOSONG berarti TIDAK ADA yang dibatasi. ──
     *
     * Kebalikannya — "belum ada aturan, jadi tolak semua" — mengunci seluruh
     * admin pada migrasi pertama, sebelum ada satu pun orang yang bisa masuk
     * untuk membuat aturannya. Fitur keamanan yang menyalakan dirinya sendiri
     * dengan cara mengusir semua orang bukan fitur keamanan; ia insiden.
     *
     * Aturan yang sama berlaku per pengguna, dan itulah yang membuat kolom
     * "Access Scope" punya arti: kalau tidak ada satu pun aturan yang menyasar
     * seseorang, ia tidak dibatasi. Begitu ADA — lewat "All Admins", perannya,
     * atau namanya — ia harus cocok dengan salah satunya.
     *
     * @param  Collection<int, IpWhitelistRule>  $rules  aturan yang menyasar pengguna itu
     */
    public static function allowsWith(Collection $rules, string $ip): bool
    {
        if ($rules->isEmpty()) {
            return true;
        }

        return $rules->contains(fn (IpWhitelistRule $rule) => self::matches($rule->ip_range, $ip));
    }

    /** Boleh masuk atau tidak, dibaca dari keadaan tabel sekarang. */
    public static function allows(User $user, string $ip): bool
    {
        return self::allowsWith(self::rulesTargeting($user), $ip);
    }

    /**
     * Apakah perubahan yang BELUM disimpan akan mengunci pemakainya sendiri.
     *
     * Desain memperingatkannya sebagai kalimat — "Deactivating or deleting a
     * rule that matches your current session may block your own access"
     * (`527:8163`) — dan kalimat tidak menghentikan apa pun. Ini versinya yang
     * benar-benar menahan.
     *
     * Cara kerjanya: ambil aturan yang menyasar pengguna ini, buang yang sedang
     * diubah, masukkan versi barunya (kalau masih akan menyasar dan masih
     * berlaku), lalu jalankan penilaian yang sama dengan middleware.
     *
     * @param  IpWhitelistRule|null  $replacement  versi sesudah disimpan; `null` untuk penghapusan
     */
    public static function wouldLockOut(User $user, string $ip, ?int $changingId, ?IpWhitelistRule $replacement): bool
    {
        $rules = self::rulesTargeting($user)
            ->reject(fn (IpWhitelistRule $rule) => $changingId !== null && $rule->id === $changingId)
            ->values();

        if ($replacement !== null && self::targets($replacement, $user) && self::isEnforceable($replacement)) {
            $rules->push($replacement);
        }

        return ! self::allowsWith($rules, $ip);
    }

    /** Apakah aturan ini menyasar pengguna tersebut. */
    public static function targets(IpWhitelistRule $rule, User $user): bool
    {
        return match ($rule->scope) {
            IpWhitelistRule::SCOPE_ALL => true,
            IpWhitelistRule::SCOPE_ROLE => $user->roles->pluck('id')->contains($rule->role_id),
            IpWhitelistRule::SCOPE_USER => (int) $rule->user_id === (int) $user->id,
            default => false,
        };
    }

    /**
     * Versi PHP dari scope `enforceable()` — dipakai untuk baris yang belum
     * ada di database, jadi tidak bisa lewat query.
     */
    public static function isEnforceable(IpWhitelistRule $rule): bool
    {
        if (! $rule->is_active) {
            return false;
        }

        if ($rule->validity !== IpWhitelistRule::VALIDITY_TEMPORARY) {
            return true;
        }

        return $rule->expires_at !== null && $rule->expires_at->isFuture();
    }

    /** `203.0.113.0/24` -> `203.0.113.0`; alamat polos dikembalikan apa adanya. */
    private static function addressPart(string $pattern): string
    {
        return trim(explode('/', trim($pattern), 2)[0]);
    }
}
