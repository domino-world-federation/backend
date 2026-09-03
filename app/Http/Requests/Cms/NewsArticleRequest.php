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
     * Rasio dan ukuran minimumnya diambil dari `dwf.uploads.image_specs`, bukan
     * diketik di sini — angka yang sama muncul di tiga tempat (aturan validasi,
     * kalimat galat, dan hint di bawah label) dan tiga salinan pasti berpisah.
     *
     * Wajib hanya saat MEMBUAT. Kalau wajib juga saat menyunting, memperbaiki
     * satu typo di judul memaksa mengunggah ulang gambar yang sudah ada.
     *
     * @return array<int, string>
     */
    private function imageRules(string $slot, bool $isCreate): array
    {
        $uploads = config('dwf.uploads');
        $spec = $uploads['image_specs'][$slot];

        return [
            $isCreate ? 'required' : 'nullable',
            'image',
            'mimes:'.implode(',', $uploads['image_mimes']),
            'max:'.$uploads['image_max_kb'],
            'dimensions:min_width='.$spec['min_width']
                .',min_height='.$spec['min_height']
                .',ratio='.$spec['ratio'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $specs = config('dwf.uploads.image_specs');

        return [
            'published_at.required_if' => __('backoffice.news.schedule_required'),
            // Kalimat `dimensions` bawaan Laravel cuma bilang "dimensinya
            // salah". Yang perlu diketahui orangnya justru angkanya.
            'hero.dimensions' => $this->dimensionMessage('hero', $specs['hero']),
            'landscape.dimensions' => $this->dimensionMessage('landscape', $specs['landscape']),
        ];
    }

    /** @param array{min_width: int, min_height: int, ratio: string} $spec */
    private function dimensionMessage(string $slot, array $spec): string
    {
        $ratio = str_replace('/', ':', $spec['ratio']);

        return __('backoffice.news.image_dimensions', [
            'width' => $spec['min_width'],
            'height' => $spec['min_height'],
            'ratio' => $ratio,
        ]);
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
