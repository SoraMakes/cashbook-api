<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Entry extends Model {
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'category_id',
        'amount',
        'recipient_sender',
        'payment_method',
        'description',
        'no_invoice',
        'date',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected array $dates = ['deleted_at', 'date', 'created_at', 'updated_at'];

    /**
     * Get the user that owns the entry.
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the category associated with the entry.
     */
    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the document associated with the entry.
     */
    public function document(): \Illuminate\Database\Eloquent\Relations\HasMany {
        return $this->hasMany(Document::class);
    }

    public function previous_version(): \Illuminate\Database\Eloquent\Relations\BelongsTo {
        return $this->belongsTo(Entry::class, 'entry_id');
    }

    public function next_version(): \Illuminate\Database\Eloquent\Relations\HasOne {
        return $this->hasOne(Entry::class, 'entry_id');
    }

    /**
     * Clone the entry and return a new instance.
     */
    public function replicateWithHistory($updatedData, $userId): Entry {
        $newEntry = $this->replicate();
        $newEntry->fill($updatedData);
        $newEntry->user_id = $userId;
        $newEntry->created_at = $this->created_at; // retain original creation timestamp
        $newEntry->entry_id = $this->id; // set the entry_id to the original entry's id
        return $newEntry;
    }
}
