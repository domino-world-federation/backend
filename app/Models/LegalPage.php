<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Models\Concerns\TracksEditor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['key', 'title', 'slug', 'last_updated_at', 'updated_by_id'])]
class LegalPage extends Model
{
    use RecordsActivity, TracksEditor;

    /** Dua halaman, kunci tetap — tidak ada layar untuk menambah yang ketiga. */
    public const KEYS = ['privacy-policy', 'terms'];

    protected function casts(): array
    {
        return ['last_updated_at' => 'date'];
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(LegalPageBlock::class)->orderBy('position')->orderBy('id');
    }
}
