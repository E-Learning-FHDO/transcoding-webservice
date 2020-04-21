<?php

namespace App\Models;

use App\User;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    protected $guarded = [];

    protected $fillable = [
       'user_id', 'download_id', 'title', 'mediakey', 'disk', 'path', 'file', 'processed', 'target', 'converted_at', 'downloaded_at', 'failed_at'
    ];

    public $attributes = [];

    protected $casts = ['target' => 'json'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function download()
    {
        return $this->belongsTo(Download::class, 'download_id', 'id');
    }
}
