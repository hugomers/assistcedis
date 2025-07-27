<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeasonsVA extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'seasons';
    public $timestamps = false;

    public function rules(){
        return $this->hasMany('App\Models\SeasonsRulesVA', '_season', 'id');
    }
}
