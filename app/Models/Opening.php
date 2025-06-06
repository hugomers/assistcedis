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
}
