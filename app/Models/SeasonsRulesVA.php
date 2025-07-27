<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeasonsRulesVA extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'season_bussines_rules';
    public $timestamps = false;
}
