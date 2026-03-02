<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutputLog extends Model
{
    protected $table = 'output_logs';
    protected $fillable = [
        '_output',
        '_state',
        '_user',
        'details',
        'created_at',
        'updated_at',
    ];
}
