<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssistJustification extends Model
{
    protected $table = "assist_justifications";

    public function createdBy(){ return $this->belongsTo('\App\Models\User','_created_by','id'); }
    public function user(){ return $this->belongsTo('\App\Models\User','_user','id'); }
    public function type(){ return $this->belongsTo('\App\Models\JustificationType','_type','id'); }
    public function state(){ return $this->belongsTo('\App\Models\JustificationState','_state','id'); }
    public function paymen(){ return $this->belongsTo('\App\Models\PaymentPercentage','_pay_percentage','id'); }


}
