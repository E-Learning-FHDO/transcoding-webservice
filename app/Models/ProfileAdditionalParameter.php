<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProfileAdditionalParameter extends Model
{
    protected $table = 'profile_additional_parameters';
    protected $fillable = ['profile_id', 'key', 'value', 'description'];

    public function profile()
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }
}