<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Opening extends Model
{
    protected $fillable = [
        '_store',
        '_cashier',
        '_type',
        'cash',
        'cash_name',
        '_created_by',
        'current_cut',
        'unsquare',
        'mismatch_reason',
        'ticket',
        'refund_ticket',
        'refund_reason',
        'withdrawal_number',
        'movement_type_id',
        'reason_modify',
        'withdrawal_mount',
        'details_cut',
        'print'
    ];
    protected $table = 'openin_boxes';


    public function createdby(){
        return $this->belongsTo('App\Models\Staff', '_created_by', 'id');
    }
    public function cashier(){
        return $this->belongsTo('App\Models\Staff', '_cashier', 'id');
    }
    public function type(){
        return $this->belongsTo('App\Models\OpeningType', '_type', 'id');
    }
    public function store(){
        return $this->belongsTo('App\Models\Stores', '_store', 'id');
    }




}
