<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CellerSectionVA extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'celler_section';

        public function celler(){
        return $this->belongsTo('App\Models\CellerVA', '_celler');
    }

}
