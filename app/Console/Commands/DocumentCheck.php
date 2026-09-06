<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Support\Media\StoredFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Mencari tahu KENAPA sebuah unduhan dokumen tidak bisa diambil.
 *
 * Berkas dokumen disajikan nginx langsung dari folder media sejak 2026-09-06,
 * jadi yang menjawab permintaannya bukan aplikasi ini — dan kalau jawabannya
 * 404, aplikasi ini tidak melihat apa pun di lognya. Yang bisa dilakukannya
 * adalah memeriksa apakah berkas yang dijanjikan barisnya memang ada di folder
 * yang URL-nya menunjuk ke sana.
 *
 * Yang dicetak lebih dulu adalah folder dan URL media yang BENAR-BENAR dibaca
 * aplikasi — bukan path yang ditulis di `.env`, yang bisa berbeda kalau
 * `bootstrap/cache/config.php` basi. Alasan yang sama dengan `dwf:mail-test`.
 *
 * Keterbacaan diperiksa terpisah dari keberadaan, dan direktori yang tidak bisa
 * ditelusuri tidak dilaporkan sebagai berkas hilang. Keduanya lahir dari
 * kegagalan alat ini sendiri pada 2026-09-05, dan catatannya ada di masing-masing
 * cabang di bawah.
 */
class DocumentCheck extends Command
{
    protected $signature = 'dwf:document-check {id? : Periksa satu dokumen saja}';

    protected $description = 'Periksa berkas tiap dokumen: ada di mana, terbaca atau tidak, dan kenapa unduhannya 404';

    /** Nama user yang menjalankan proses ini — yang menentukan apa yang boleh ia lihat. */
    private static function currentUser(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            return posix_getpwuid(posix_geteuid())['name'] ?? 'tidak diketahui';
        }

        return get_current_user() ?: 'tidak diketahui';
    }

    /** Direktori induk sebuah berkas di folder media, sebagai path absolut. */
    private static function parentDirectory(string $root, string $path): string
    {
        return dirname(rtrim($root, '/').'/'.ltrim($path, '/'));
    }

    public function handle(): int
    {
        $media = Storage::disk('public');

        $this->line('');
        $this->line('  Dijalankan sebagai <fg=yellow>'.self::currentUser().'</>  <fg=gray>(web berjalan sebagai user lain — lihat catatan di bawah)</>');
        $this->line('');
        $this->line('  Media yang <fg=yellow>benar-benar dibaca aplikasi</> (bukan isi .env):');
        $this->line('    folder : '.config('filesystems.disks.public.root'));
        $this->line('    URL    : '.config('filesystems.disks.public.url'));
        $this->line('');

        $documents = Document::query()
            ->when($this->argument('id'), fn ($q, $id) => $q->whereKey($id))
            ->orderBy('id')
            ->get();

        if ($documents->isEmpty()) {
            $this->warn('Tidak ada dokumen.');

            return self::SUCCESS;
        }

        $broken = 0;
        $undetermined = 0;

        foreach ($documents as $document) {
            $live = Document::query()->live()->whereKey($document->getKey())->exists();
            $onDisk = $media->exists($document->file_path);

            $this->line("  <options=bold>#{$document->id}</> {$document->title}");
            $this->line('     status : '.$document->status.($live ? ', tayang' : ', <fg=yellow>BELUM tayang</> <fg=gray>(barisnya tersembunyi; berkasnya tetap bisa diunduh)</>'));
            $this->line('     path   : '.$document->file_path);
            $this->line('     berkas : '.($onDisk ? '<fg=green>ADA</>' : '<fg=red>TIDAK ADA</>'));
            $this->line('     URL    : '.(StoredFile::url($document->file_path) ?? '-'));

            /*
             * Berkas yang ADA tapi tidak terbaca proses ini terlihat persis sama
             * dengan berkas yang tidak ada — `file_exists()` mengembalikan false
             * pada keduanya kalau direktori induknya tidak bisa ditelusuri.
             */
            if ($onDisk) {
                $absolute = $media->path($document->file_path);
                $this->line('     terbaca: '.(is_readable($absolute) ? '<fg=green>ya</>' : '<fg=red>TIDAK — periksa izin berkas dan direktori induknya</>'));
                $this->line('');

                continue;
            }

            /*
             * "Tidak ada" dan "tidak boleh melihat" terlihat SAMA dari sini, dan
             * membedakannya adalah seluruh guna perintah ini. Terjadi
             * 2026-09-05: dokumen diunggah oleh php-fpm, yang membuat
             * direktorinya 0700 miliknya sendiri; perintah dijalankan dari shell
             * sebagai user lain dan melaporkan berkasnya hilang padahal ia cuma
             * tidak boleh melihat.
             */
            $directory = self::parentDirectory((string) config('filesystems.disks.public.root'), $document->file_path);

            if (is_dir($directory) && ! is_executable($directory)) {
                $this->line('');
                $this->line('     <fg=yellow>=> TIDAK BISA DIPASTIKAN dari user ini.</> Direktorinya ada tapi tidak bisa');
                $this->line('        ditelusuri oleh <fg=yellow>'.self::currentUser().'</>, jadi "TIDAK ADA" di atas tidak sah —');
                $this->line('        berkas yang ADA pun terbaca hilang. Ulangi sebagai user web:');
                $this->line('           <fg=yellow>sudo -u www-data php artisan dwf:document-check '.$document->id.'</>');
                $this->line('');
                $undetermined++;

                continue;
            }

            $broken++;
            $this->line('');
            $this->line('     <fg=red>=> Berkasnya tidak ada di folder media.</> Kemungkinannya, berurutan dari');
            $this->line('        yang paling sering:');
            $this->line('        1. MEDIA_ROOT menunjuk tempat lain daripada saat berkas diunggah;');
            $this->line('        2. folder media tidak ikut terbawa saat pindah server atau restore;');
            $this->line('        3. barisnya dibuat tanpa unggahan sungguhan (baris seed).');
            $this->line('        Kalau berkasnya masih ada di suatu tempat, salin ke:');
            $this->line('           <fg=yellow>'.rtrim((string) config('filesystems.disks.public.root'), '/').'/'.$document->file_path.'</>');
            $this->line('');
        }

        if ($broken > 0) {
            $this->error("{$broken} dokumen tidak punya berkasnya — unduhannya 404.");

            return self::FAILURE;
        }

        /*
         * Tidak tahu BUKAN kabar baik, dan tidak boleh terbaca begitu.
         *
         * Menutup dengan "semua dokumen punya berkasnya" setelah gagal melihat
         * sebagiannya adalah kesalahan yang sama dengan yang perintah ini ada
         * untuk mencegahnya, cuma dalam bentuk ringkasan. Keluar bukan-nol juga,
         * supaya skrip yang memakainya tidak menganggapnya lulus.
         */
        if ($undetermined > 0) {
            $this->warn("{$undetermined} dokumen tidak bisa dipastikan dari user ".self::currentUser().' — ulangi sebagai user web.');

            return self::FAILURE;
        }

        $this->info('Semua dokumen punya berkasnya.');

        return self::SUCCESS;
    }
}
