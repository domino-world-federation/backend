<?php

namespace App\Models;

use App\Models\Concerns\HasPosition;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['legal_page_id', 'title', 'description', 'is_active', 'position'])]
class LegalPageBlock extends Model
{
    use HasPosition, RecordsActivity;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(LegalPage::class, 'legal_page_id');
    }
}
