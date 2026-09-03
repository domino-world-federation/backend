<?php

namespace App\Models;

use App\Models\Concerns\HasPosition;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'is_active', 'position'])]
class NewsCategory extends Model
{
    use HasFactory, HasPosition, HasSlug, RecordsActivity;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function articles(): HasMany
    {
        return $this->hasMany(NewsArticle::class);
    }
}
