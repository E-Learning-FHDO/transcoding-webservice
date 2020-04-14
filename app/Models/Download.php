<?php

namespace App\Models;

use App\User;
use Illuminate\Database\Eloquent\Model;

class Download extends Model
{
    protected $fillable = ['user_id', 'mediakey', 'payload', 'processed'];

    protected $guarded = [];

    public $attributes = [];

    protected $casts = ['payload' => 'json'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function videos()
    {
        return $this->hasMany(Video::class, 'download_id', 'id');
    }
}
