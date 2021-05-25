<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ProfileOption;
use App\Models\ProfileAdditionalParameter;

class Profile extends Model
{
    protected $table = 'profiles';


    public function options()
    {
        return $this->hasMany(ProfileOption::class);
    }

    public function additionalparameters()
    {
        return $this->hasMany(ProfileAdditionalParameter::class);
    }

    public function workers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Worker::class, 'profile_workers', 'profile_id', 'worker_id');
    }
}
