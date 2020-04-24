<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DownloadJob extends Model
{
    protected $table = 'download_jobs';
    protected $fillable = ['download_id', 'job_id', 'created_at', 'updated_at'];
}
