<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderProcessVA extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'order_process';
    protected $guarded = [];

    /* public function orders(){
        return $this->belongsToMany('App\Order', 'order_log', '_status', '_order')
        ->withPivot('id', 'details')
        ->withTimestamps();
    } */
    public function orders(){
        return $this->belongsToMany('App\Models\OrderVA', 'order_log', '_status', '_order')->using('App\Models\OrderLog')->withPivot('_responsable', '_type', 'details', 'created_at');
    }

    /* public function config(){
        return $this->hasMany('App\OrderProcessConfig', '_process', 'id');
    } */
    public function config(){
        return $this->belongsToMany('App\Models\WorkpointVA', 'order_process_config', '_process', '_workpoint')
        ->withPivot('active', 'details');
    }

}
