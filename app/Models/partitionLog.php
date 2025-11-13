<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class partitionLog extends Model
    {
    protected $connection = 'vizapi';
    protected $table = 'partition_logs';
    protected $fillable = [
        "_requisition",
        "_partition",
        "_status",
        "details",
        "created_at",
        "updated_at",
    ];

    }
