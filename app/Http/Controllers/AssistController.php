<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Exports\AssistExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;


class AssistController extends Controller
{
    public function report(){
            $semana = now()->format('W');
            $anio = now()->format('Y');
            $name = "reporteasistencia_sem_".$semana;
            return Excel::download(new AssistExport, $name.'.xlsx');
    }
}
