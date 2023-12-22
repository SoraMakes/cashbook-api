<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'entry_id',
        'file_path',
        // Add any other fields you want to be mass assignable
    ];

    /**
     * Get the entry associated with the document.
     */
    public function entry(): \Illuminate\Database\Eloquent\Relations\BelongsTo {
        return $this->belongsTo(Entry::class);
    }

    // Add any other relationships or custom methods as needed
}
