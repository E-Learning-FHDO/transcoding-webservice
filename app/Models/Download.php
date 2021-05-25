<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use \Repat\LaravelJobs\Job;

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

    public function media()
    {
        return $this->hasMany(Media::class, 'download_id', 'id');
    }

    public function jobs(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Job::class, 'download_jobs', 'download_id', 'job_id');
    }
}
