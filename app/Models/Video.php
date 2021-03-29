<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    protected $guarded = [];

    protected $fillable = [
       'user_id', 'download_id', 'title', 'mediakey', 'disk', 'path', 'file', 'processed', 'percentage', 'worker', 'target', 'converted_at', 'downloaded_at', 'failed_at'
    ];

    protected $casts = ['target' => 'json'];

    public const UNPROCESSED = 0;
    public const PROCESSED = 1;
    public const PROCESSING = 2;
    public const FAILED = 3;

    public static $status = [
        '0' => 'unprocessed',
        '1' => 'processed',
        '2' => 'processing',
        '3' => 'failed',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function download()
    {
        return $this->belongsTo(Download::class, 'download_id', 'id');
    }
}
