<?php

namespace App\Console\Commands;

use App\Models\NewsletterSubscriber;
use App\Notifications\NewsletterDigest;
use App\Support\SubmissionNotifier;
use Illuminate\Console\Command;

/**
 * Ringkasan pendaftar buletin, sehari sekali.
 *
 * Ini satu-satunya dari tiga formulir yang TIDAK memberitahu per kejadian, dan
 * alasannya ada di `NewsletterDigest`.
 *
 * Menghitung yang MASIH berlangganan dan mendaftar dalam jendela yang diminta.
 * Orang yang mendaftar lalu berhenti pada hari yang sama tidak dihitung — yang
 * ditanyakan penerima ringkasan ini adalah "daftarnya bertambah berapa",
 * bukan "berapa kali tombolnya ditekan".
 */
class NewsletterDigestCommand extends Command
{
    protected $signature = 'dwf:newsletter-digest {--hours=24 : Jendela yang dirangkum, dalam jam}';

    protected $description = 'Kirim ringkasan pendaftar buletin baru ke kotak masuk federasi';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $since = now()->subHours($hours);

        $new = NewsletterSubscriber::query()
            ->subscribed()
            ->where('created_at', '>=', $since)
            ->count();

        /*
         * Nol berarti tidak ada surel sama sekali.
         *
         * "0 pendaftar baru hari ini" adalah pemberitahuan yang tidak memberi
         * tahu apa pun sambil tetap menuntut dibuka, dan sesudah beberapa
         * minggu ia melatih orang menghapus surel dari sistem ini tanpa
         * membacanya.
         */
        if ($new === 0) {
            $this->line("Tidak ada pendaftar baru dalam {$hours} jam terakhir — tidak ada yang dikirim.");

            return self::SUCCESS;
        }

        $total = NewsletterSubscriber::query()->subscribed()->count();

        SubmissionNotifier::send('newsletter.view', new NewsletterDigest($new, $total));

        $this->info("Ringkasan dikirim: {$new} pendaftar baru, {$total} total.");

        return self::SUCCESS;
    }
}
