<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'type', 'held_on'])]
class GalleryEvent extends Model
{
    use HasFactory, HasSlug, RecordsActivity;

    public const TYPES = ['event', 'tournament'];

    protected function casts(): array
    {
        return ['held_on' => 'date'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(GalleryItem::class);
    }
}
