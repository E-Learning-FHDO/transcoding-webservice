<?php

namespace App\Models;

use App\User;
use Illuminate\Database\Eloquent\Model;

class Download extends Model
{
    public const UNPROCESSED = 0;
    public const PROCESSED = 1;
    public const PROCESSING = 2;
    public const FAILED = 3;


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
