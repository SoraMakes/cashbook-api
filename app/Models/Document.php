<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model {
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'entry_id',
        'original_path',
        'document_path',
        'original_filename',
        'thumbnail_path',
    ];

    protected $hidden = ['deleted_at', 'file_path', 'thumbnail_path'];

    /**
     * Get the entry associated with the document.
     */
    public function entry(): \Illuminate\Database\Eloquent\Relations\BelongsTo {
        return $this->belongsTo(Entry::class);
    }

    // Add any other relationships or custom methods as needed
}
