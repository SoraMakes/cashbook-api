<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class Document extends Model {
    use SoftDeletes;

    public static function boot() {
        parent::boot();

        static::deleting(function($document) {
            $document->update(['user_id_last_modified' => Auth::id()]);
        });
    }

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
        'user_id',
        'user_id_last_modified',
    ];

    protected $hidden = ['deleted_at', 'file_path', 'thumbnail_path'];

    /**
     * Get the entry associated with the document.
     */
    public function entry(): \Illuminate\Database\Eloquent\Relations\BelongsTo {
        return $this->belongsTo(Entry::class);
    }
}
