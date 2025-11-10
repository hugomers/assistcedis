<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\Pivot;

// class OrderLogVA extends Model
class OrderLogVA extends Pivot

{
    protected $connection = 'vizapi';
    protected $table = 'order_log';
    public $incrementing = true;
    public $timestamps = true;
    const UPDATED_AT = null;
    protected $fillable = ["id", '_order', '_status', '_responsable', '_type', 'details', 'created_at'];

    public function responsable(){
        return $this->morphTo(null, '_type', '_responsable');
    }
}
