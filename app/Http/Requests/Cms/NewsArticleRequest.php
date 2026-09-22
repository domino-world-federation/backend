<?php

namespace App\Http\Requests\Cms;

use App\Models\NewsArticle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NewsArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $isCreate = $this->route('article') === null;

        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:200'],
            'news_category_id' => ['required', 'integer', 'exists:news_categories,id'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string'],
            'is_highlighted' => ['required', 'boolean'],

            // Gambar hero wajib saat membuat, opsional saat menyunting — kalau
            // wajib juga saat menyunting, mengubah judul memaksa mengunggah
            // ulang gambar yang sudah ada.
            'hero' => $this->imageRules('hero', $isCreate),
            'landscape' => $this->imageRules('landscape', $isCreate),

            'posting' => ['required', Rule::in(['now', 'schedule', 'draft'])],
            'published_at' => ['nullable', 'required_if:posting,schedule', 'date'],
        ];
    }

    /**
     * Aturan untuk satu slot gambar.
     *
     * TANPA `dimensions:`. Ukuran dan rasio di `dwf.uploads.image_specs` tetap
     * ada, tapi kini SARAN — ia mengisi hint di bawah label dan menggambar
     * demo image, bukan menolak unggahan. Angkanya dibaca dari label
     * "Recommended size" di desain, dan menegakkan saran sebagai syarat berarti
     * redaksi yang punya foto bagus dengan potongan lain tidak bisa menerbitkan
     * sama sekali. Yang memotong ke kotak desain adalah `object-cover` di situs
     * publik, bukan form ini.
     *
     * Yang tersisa: format dan berat. Keduanya bukan selera — WebP dan batas
     * 1 MB menentukan apakah halamannya terbuka, bukan apakah ia rapi.
     *
     * Wajib hanya saat MEMBUAT. Kalau wajib juga saat menyunting, memperbaiki
     * satu typo di judul memaksa mengunggah ulang gambar yang sudah ada.
     *
     * @return array<int, string>
     */
    private function imageRules(string $slot, bool $isCreate): array
    {
        $uploads = config('dwf.uploads');

        return [
            $isCreate ? 'required' : 'nullable',
            'image',
            'mimes:'.implode(',', $uploads['image_mimes']),
            'max:'.$uploads['image_max_kb'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'published_at.required_if' => __('backoffice.news.schedule_required'),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'news_category_id' => 'kategori',
            'body' => 'isi berita',
            'hero' => 'gambar hero',
            'landscape' => 'gambar',
            'published_at' => 'waktu tayang',
        ];
    }

    /**
     * Tombolnya tiga (Save Draft / Posting sekarang / Schedule) tapi kolomnya
     * satu. Pemetaannya dikunci di sini supaya controller tidak perlu menebak.
     */
    public function resolvedStatus(): string
    {
        return match ($this->string('posting')->toString()) {
            'draft' => NewsArticle::STATUS_DRAFT,
            'schedule' => NewsArticle::STATUS_SCHEDULED,
            default => NewsArticle::STATUS_PUBLISHED,
        };
    }

    public function resolvedPublishedAt(): ?CarbonImmutable
    {
        return match ($this->string('posting')->toString()) {
            'draft' => null,
            'schedule' => CarbonImmutable::parse($this->string('published_at')->toString()),
            default => CarbonImmutable::now(),
        };
    }

    /** Slug boleh ditulis tangan; kalau kosong ia diturunkan dari judul. */
    public function slugSource(): string
    {
        $slug = $this->string('slug')->toString();

        return $slug !== '' ? $slug : $this->string('title')->toString();
    }
}
