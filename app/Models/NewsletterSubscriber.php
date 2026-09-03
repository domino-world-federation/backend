<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['email', 'unsubscribed_at'])]
class NewsletterSubscriber extends Model
{
    use HasFactory, RecordsActivity;

    protected function casts(): array
    {
        return ['unsubscribed_at' => 'datetime'];
    }

    public function scopeSubscribed(Builder $query): Builder
    {
        return $query->whereNull('unsubscribed_at');
    }

    public function isSubscribed(): bool
    {
        return $this->unsubscribed_at === null;
    }
}
