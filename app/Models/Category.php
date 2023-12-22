<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model {
    use SoftDeletes;

    protected $fillable = ['name'];

    protected $hidden = ['deleted_at'];

    protected array $dates = ['deleted_at'];

    public $timestamps = false;

    public function entries(): \Illuminate\Database\Eloquent\Relations\HasMany {
        return $this->hasMany(Entry::class);
    }
}
