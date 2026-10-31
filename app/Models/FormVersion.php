<?php

namespace App\Models;

use App\Enums\FormVersionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormVersion extends Model
{
    protected $fillable = ['form_definition_id', 'version', 'status', 'schema', 'ui_schema', 'configuration', 'published_at', 'published_by'];

    protected function casts(): array
    {
        return ['status' => FormVersionStatus::class, 'schema' => 'array', 'ui_schema' => 'array', 'configuration' => 'array', 'published_at' => 'datetime'];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(FormDefinition::class, 'form_definition_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('sort_order');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function isMutable(): bool
    {
        return $this->status === FormVersionStatus::Draft;
    }
}
