<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Worker extends Model
{
    protected $guarded = [];

    protected $fillable = [
        'host', 'description', 'last_seen_at'
    ];
}
