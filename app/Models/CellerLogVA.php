<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CellerLogVA extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'celler_log';
    protected $fillable = ['details', '_celler'];
    public $timestamps = false;


    public function celler(){
        return $this->belongsTo('App\Models\CellerVA', '_celler');
    }
}
