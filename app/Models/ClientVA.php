<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientVA extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'client';
    protected $fillable = ['name', "phone", "email", "rfc", "address", "_price_list"];

    /*****************
     * Relationships *
     *****************/
    public function sales(){
        return $this->hasMany('App\SalesVA', "_client");
    }
}
