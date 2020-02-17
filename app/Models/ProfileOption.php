<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProfileOption extends Model
{
    protected $table = 'profile_options';
    protected $fillable = ['profile_id', 'key', 'value', 'description'];

    public function profile()
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }
}