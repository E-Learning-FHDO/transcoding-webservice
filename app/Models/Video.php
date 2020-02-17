<?php

namespace App\Models;

use App\User;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    protected $guarded = [];

    protected $fillable = [
       'uid', 'title', 'mediakey', 'disk', 'path', 'file', 'processed', 'target', 'converted_at'
    ];

    public $attributes = [];

    protected $casts = ['target' => 'json'];

    public function user()
    {
        return $this->belongsTo(User::class, 'uid', 'id');
    }
}
