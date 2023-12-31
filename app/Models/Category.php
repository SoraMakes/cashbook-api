<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model {
    use SoftDeletes;

    protected $fillable = ['name', 'user_id', 'user_id_last_modified'];

    protected $hidden = ['deleted_at'];

    protected array $dates = ['deleted_at'];

    public $timestamps = false;

    public function entries(): \Illuminate\Database\Eloquent\Relations\HasMany {
        return $this->hasMany(Entry::class);
    }
}
