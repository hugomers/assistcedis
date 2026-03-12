<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    protected $table = "quiz";
    protected $fillable = [
        "fifth",
        "fifthno",
        "first",
        "fourth",
        "second",
        "sixth",
        "third",
        "ticket",
        "_cashier",
        "_seller",
        "_store"
    ];


    public function store()
    {
        return $this->belongsTo(Stores::class,'_store');
    }

    public function seller()
    {
        return $this->belongsTo(Staff::class,'_seller');
    }

    public function cashier()
    {
        return $this->belongsTo(User::class,'_cashier');
    }
}
