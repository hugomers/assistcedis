<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Staff;

class cashierController extends Controller
{
    public function getStaff($id){
        $staff = Staff::where([['_store',$id], ['_position',11]])->get();
        return $staff;
    }

}
