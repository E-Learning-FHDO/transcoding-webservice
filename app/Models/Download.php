<?php

namespace App\Models;

use App\User;
use Illuminate\Database\Eloquent\Model;

class Download extends Model
{
    protected $fillable = ['uid', 'payload', 'processed'];

    protected $guarded = [];

    public $attributes = [];

    protected $casts = ['payload' => 'json'];

    public function user()
    {
        return $this->belongsTo(User::class, 'uid', 'id');
    }
}
