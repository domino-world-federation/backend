<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, string> */
    private function flatten(array $lines, string $prefix = ''): array
    {
        $keys = [];

        foreach ($lines as $key => $value) {
            $full = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            $keys = is_array($value)
                ? array_merge($keys, $this->flatten($value, $full))
                : array_merge($keys, [$full]);
        }

        return $keys;
    }

    public function test_both_locales_carry_exactly_the_same_keys(): void
    {
        // Penjaga terpenting berkas terjemahan: kunci yang hanya ada di satu
        // sisi TIDAK menghasilkan galat saat runtime — `t()` mengembalikan
        // kuncinya apa adanya, dan yang muncul di layar adalah teks seperti
        // "news.field_title". Tes ini yang menangkapnya sebelum orang lain.
        $en = $this->flatten(require lang_path('en/backoffice.php'));
        $id = $this->flatten(require lang_path('id/backoffice.php'));

        sort($en);
        sort($id);

        $this->assertSame($en, $id, 'Struktur kunci lang/en dan lang/id berbeda.');
    }

    public function test_no_translation_line_is_left_empty(): void
    {
        foreach (['en', 'id'] as $locale) {
            $lines = require lang_path("{$locale}/backoffice.php");

            array_walk_recursive($lines, function ($value, $key) use ($locale) {
                $this->assertNotSame('', trim((string) $value), "{$locale}: kunci '{$key}' kosong.");
            });
        }
    }

    public function test_the_dictionary_reaches_the_page(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('locale', Locales::DEFAULT)
                ->has('translations.common.save')
            );
    }

    public function test_english_is_the_default(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('locale', 'en')
                ->where('translations.common.save', 'Save')
            );
    }

    public function test_the_switcher_is_hidden_by_default(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('localeSwitchable', false)
                ->has('locales', 0)
            );
    }

    public function test_switching_is_refused_while_the_switcher_is_off(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->put('/locale', ['locale' => 'id'])
            ->assertNotFound();
    }

    public function test_a_stored_preference_is_ignored_while_the_switcher_is_off(): void
    {
        // Jebakan yang paling mudah terlewat: mematikan pengalih TANPA
        // mengabaikan `users.locale` akan mengunci siapa pun yang pernah
        // memilih bahasa lain — preferensinya tetap terbaca, tombol untuk
        // mengubahnya sudah hilang.
        $user = User::factory()->superAdmin()->create(['locale' => 'id']);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('locale', 'en'));
    }

    public function test_switching_locale_changes_the_dictionary_and_sticks_to_the_user(): void
    {
        config(['dwf.locale_switcher' => true]);
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)->put('/locale', ['locale' => 'id'])->assertRedirect();

        $this->assertSame('id', $user->fresh()->locale);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('locale', 'id')
                ->where('translations.common.save', 'Simpan')
            );
    }

    public function test_a_guest_can_switch_before_signing_in(): void
    {
        config(['dwf.locale_switcher' => true]);

        // Halaman login juga diterjemahkan, dan di sana belum ada user yang
        // bisa menyimpan preferensinya.
        $this->put('/locale', ['locale' => 'id'])->assertRedirect();

        $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page->where('locale', 'id'));
    }

    public function test_an_unsupported_locale_is_rejected(): void
    {
        config(['dwf.locale_switcher' => true]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->put('/locale', ['locale' => '../../../etc/passwd'])
            ->assertSessionHasErrors('locale');
    }

    public function test_validation_messages_follow_the_chosen_locale(): void
    {
        // Ini yang membuat satu sumber terjemahan berarti: pesan validasi
        // datang dari server, label tombol dari kamus yang sama — keduanya
        // tidak boleh berbeda bahasa di layar yang sama.
        config(['dwf.locale_switcher' => true]);
        $user = User::factory()->superAdmin()->create(['locale' => 'id']);

        $this->actingAs($user)
            ->post('/news/categories', ['name' => '', 'is_active' => true])
            ->assertSessionHasErrors('name');

        $this->assertStringContainsString(
            'wajib diisi',
            session('errors')->getBag('default')->first('name'),
        );

        $user->update(['locale' => 'en']);
        $this->flushSession();

        $this->actingAs($user)
            ->post('/news/categories', ['name' => '', 'is_active' => true])
            ->assertSessionHasErrors('name');

        $this->assertStringContainsString(
            'required',
            session('errors')->getBag('default')->first('name'),
        );
    }
}
