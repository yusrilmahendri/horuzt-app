<?php
namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mempelai extends Model
{
    use HasFactory;
    protected $guarded = [''];

//    public function user(){
//         return $this->belongsTo(User::class);
//     }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

}
