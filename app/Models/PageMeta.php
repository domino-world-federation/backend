<?php

namespace App\Models;

use App\Models\Concerns\HasPosition;
use App\Models\Concerns\RecordsActivity;
use App\Models\Concerns\TracksEditor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Meta SEO satu halaman publik.
 *
 * `route = '*'` adalah baris BAWAAN, dipakai halaman yang tidak punya barisnya
 * sendiri dan sebagai cadangan tiap field yang dikosongkan. Lihat migrasinya.
 */
#[Fillable(['route', 'label', 'title', 'description', 'og_image_path', 'position', 'updated_by_id'])]
class PageMeta extends Model
{
    use HasFactory, HasPosition, RecordsActivity, TracksEditor;

    protected $table = 'page_meta';

    /** Rute semu untuk baris bawaan. Bukan path yang sah, jadi tidak bertabrakan. */
    public const DEFAULT_ROUTE = '*';

    public function isDefault(): bool
    {
        return $this->route === self::DEFAULT_ROUTE;
    }
}
