<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSubmissionFile extends Model
{
    protected $fillable = ['form_submission_id', 'field_key', 'disk', 'path', 'original_name', 'mime_type', 'size', 'uploaded_by', 'metadata'];

    protected $hidden = ['disk', 'path'];

    protected $casts = ['metadata' => 'array'];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
