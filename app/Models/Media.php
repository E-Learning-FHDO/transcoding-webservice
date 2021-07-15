<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    public const QUEUE = 'media';
    protected $guarded = [];

    protected $fillable = [
        'user_id',
        'download_id',
        'title',
        'mediakey',
        'disk',
        'path',
        'file',
        'processed',
        'percentage',
        'worker',
        'target',
        'converted_at',
        'downloaded_at',
        'failed_at'
    ];

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