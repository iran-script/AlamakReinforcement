<?php

namespace App\Models;

use App\Enums\FormFieldType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormField extends Model
{
    protected $fillable = ['form_version_id', 'key', 'label', 'description', 'field_type', 'data_type', 'sort_order', 'placeholder', 'default_value', 'options', 'validation', 'visibility_condition', 'required_condition', 'access_definition', 'configuration', 'is_required', 'is_active'];

    protected function casts(): array
    {
        return ['field_type' => FormFieldType::class, 'default_value' => 'array', 'options' => 'array', 'validation' => 'array', 'visibility_condition' => 'array', 'required_condition' => 'array', 'access_definition' => 'array', 'configuration' => 'array', 'is_required' => 'boolean', 'is_active' => 'boolean'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class, 'form_version_id');
    }
}
