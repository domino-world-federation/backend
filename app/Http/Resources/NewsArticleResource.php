<?php

namespace App\Http\Resources;

use App\Support\Media\StoredFile;
use Illuminate\Http\Request;

/**
 * `NewsArticle`.
 *
 * `body` hanya ikut di layar detail — kontraknya menulis "Not guaranteed in
 * list responses", dan mengirim seluruh isi artikel di daftar berisi 24 kartu
 * berarti memindahkan ratusan kilobyte yang tidak satu pun tergambar.
 */
class NewsArticleResource extends PublicResource
{
    public function __construct($resource, private readonly bool $withBody = false)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    protected function payload(Request $request): array
    {
        return [
            'id' => $this->idString(),
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'category' => $this->category?->name,
            'publishedAt' => $this->published_at?->toIso8601String(),
            'thumbnailUrl' => StoredFile::url($this->hero_image_path),
            // Foto lanskap untuk pita featured. Terpisah dari thumbnail karena
            // keduanya potongan yang berbeda; band jatuh ke thumbnail kalau
            // kosong, dan itu keadaan normal.
            'heroImageUrl' => StoredFile::url($this->landscape_image_path),
            'heroImageAlt' => null,
            'isFeatured' => $this->is_highlighted,
            'body' => $this->withBody ? $this->body : null,
        ];
    }
}
