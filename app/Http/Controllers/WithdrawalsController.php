<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\Builder;
use App\Models\WithdrawalStore;
use App\Models\WithdrawalStatus;
use App\Models\Stores;

class WithdrawalsController extends Controller
{
    public function getWithdrawalsStore($sid){
        $withdrawals = WithdrawalStore::where('_store',$sid)->get();
        return response()->json();
    }
}
