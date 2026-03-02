<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CellerLogVA extends Model
{
    // protected $connection = 'vizapi';
    protected $table = 'location_logs';
    protected $fillable = ['_section','_user','type','details','created_at','updated_at'];
    // public $timestamps = false;
//

    public function section(){
        return $this->belongsTo('App\Models\CellerSectionVA', '_section');
    }
}
