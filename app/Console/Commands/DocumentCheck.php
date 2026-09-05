<?php

namespace App\Console\Commands;

use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Mencari tahu KENAPA sebuah unduhan dokumen 404.
 *
 * `MediaController` menolak dengan 404 untuk tiga sebab yang berbeda dan tidak
 * membedakan ketiganya di layar — sengaja, karena membedakannya berarti memberi
 * tahu orang luar apakah sebuah dokumen ADA tapi sedang ditahan. Akibatnya yang
 * mengurus server tidak bisa membedakannya juga, dan satu-satunya jalan adalah
 * menebak: apakah barisnya belum tayang, apakah berkasnya hilang, atau apakah
 * disk-nya menunjuk tempat lain.
 *
 * Perintah ini menjawab ketiganya sekaligus, dari dalam aplikasi, memakai disk
 * yang BENAR-BENAR dibaca aplikasi — bukan path yang ditulis di `.env`, yang
 * bisa berbeda kalau `bootstrap/cache/config.php` basi. Alasan yang sama dengan
 * `dwf:mail-test`.
 *
 * Disk `public` ikut diperiksa karena satu kegagalan punya bentuk yang sangat
 * khas: berkasnya ada di sana dan tidak ada di disk privat. Artinya ia diunggah
 * oleh kode SEBELUM `move_documents_to_the_private_disk`, dan migrasinya belum
 * dijalankan di mesin itu.
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

    /** Direktori induk sebuah berkas di disk privat, sebagai path absolut. */
    private static function parentDirectory(string $root, string $path): string
    {
        return dirname(rtrim($root, '/').'/'.ltrim($path, '/'));
    }

    public function handle(): int
    {
        $private = Storage::disk('local');
        $public = Storage::disk('public');

        $this->line('');
        $this->line('  Dijalankan sebagai <fg=yellow>'.self::currentUser().'</>  <fg=gray>(web berjalan sebagai user lain — lihat catatan di bawah)</>');
        $this->line('');
        $this->line('  Disk yang <fg=yellow>benar-benar dibaca aplikasi</> (bukan isi .env):');
        $this->line('    privat : '.config('filesystems.disks.local.root'));
        $this->line('    public : '.config('filesystems.disks.public.root'));
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
            $onPrivate = $private->exists($document->file_path);
            $onPublic = $public->exists($document->file_path);

            $this->line("  <options=bold>#{$document->id}</> {$document->title}");
            $this->line('     status : '.$document->status.($live ? ', tayang' : ', <fg=yellow>BELUM tayang</>'));
            $this->line('     path   : '.$document->file_path);
            $this->line('     privat : '.($onPrivate ? '<fg=green>ADA</>' : '<fg=red>TIDAK ADA</>').'   <fg=gray>(yang dicari MediaController)</>');
            $this->line('     public : '.($onPublic ? '<fg=yellow>ADA</>' : 'tidak ada'));

            /*
             * Berkas yang ADA tapi tidak terbaca proses PHP terlihat persis
             * sama dengan berkas yang tidak ada — `file_exists()` mengembalikan
             * false pada keduanya kalau direktori induknya tidak bisa ditelusuri.
             */
            if ($onPrivate) {
                $absolute = $private->path($document->file_path);
                $this->line('     terbaca: '.(is_readable($absolute) ? '<fg=green>ya</>' : '<fg=red>TIDAK — periksa izin berkas dan direktori induknya</>'));
            }

            if (! $onPrivate) {
                /*
                 * "Tidak ada" dan "tidak boleh melihat" terlihat SAMA dari sini,
                 * dan membedakannya adalah seluruh guna perintah ini.
                 *
                 * `file_exists()` menjawab false untuk berkas yang ADA kalau
                 * direktori induknya tidak bisa ditelusuri proses yang bertanya.
                 * Terjadi 2026-09-05: dokumen diunggah oleh php-fpm (www-data),
                 * yang membuat direktorinya 0700 miliknya sendiri; perintah ini
                 * dijalankan dari shell sebagai user lain, dan melaporkan
                 * berkasnya hilang padahal ia cuma tidak boleh melihat. Alat
                 * diagnosa yang salah menunjuk lebih buruk daripada tidak ada.
                 */
                $directory = self::parentDirectory((string) config('filesystems.disks.local.root'), $document->file_path);

                if (is_dir($directory) && ! is_executable($directory)) {
                    $this->line('');
                    $this->line('     <fg=yellow>=> TIDAK BISA DIPASTIKAN dari user ini.</> Direktorinya ada tapi tidak bisa');
                    $this->line('        ditelusuri oleh <fg=yellow>'.self::currentUser().'</>, jadi "tidak ada" di atas tidak sah —');
                    $this->line('        berkas yang ADA pun terbaca hilang. Ulangi sebagai user web:');
                    $this->line('           <fg=yellow>sudo -u www-data php artisan dwf:document-check '.$document->id.'</>');
                    $this->line('        Kalau di sana ia ADA, unduhannya tidak 404 karena berkas — cari sebab lain.');
                    $this->line('');
                    $undetermined++;

                    continue;
                }

                $broken++;
                $this->line('');

                if ($onPublic) {
                    $this->line('     <fg=red>=> Berkasnya tertinggal di disk public.</> Ia diunggah oleh kode sebelum');
                    $this->line('        move_documents_to_the_private_disk, dan migrasi itu belum jalan di sini:');
                    $this->line('           <fg=yellow>php artisan migrate --force</>');
                } else {
                    $this->line('     <fg=red>=> Berkasnya tidak ada di kedua disk.</> Kemungkinannya, berurutan dari');
                    $this->line('        yang paling sering:');
                    $this->line('        1. MEDIA_PRIVATE_ROOT menunjuk tempat lain daripada saat berkas diunggah');
                    $this->line('           — deploy bergaya rilis-simbolik menaruhnya di dalam rilis LAMA;');
                    $this->line('        2. storage/ tidak ikut terbawa saat pindah server atau restore;');
                    $this->line('        3. barisnya dibuat tanpa unggahan sungguhan (baris seed).');
                    $this->line('        Kalau berkasnya masih ada di suatu tempat, salin ke:');
                    $this->line('           <fg=yellow>'.rtrim((string) config('filesystems.disks.local.root'), '/').'/'.$document->file_path.'</>');
                }
            }

            $this->line('');
        }

        if ($broken > 0) {
            $this->error("{$broken} dokumen tidak punya berkas di disk privat — unduhannya 404.");

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
