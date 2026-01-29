<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserMedia extends Model
{
    protected $table = "user_media";
    protected $fillable  = [
                        "_user",
                        "_type",
                        "path",
                        "mime"
                ];

}
