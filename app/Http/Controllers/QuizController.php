<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Quiz;
use Illuminate\Support\Facades\DB;


class QuizController extends Controller
{
    public function addQuiz(Request $request){
        $resp = Quiz::create($request->all());

        return response()->json($resp);
    }
    public function getStats(Request $request){
        $month = $request->month ?? now()->month;
        $year  = $request->year ?? now()->year;
        $quiz = Quiz::with(['seller','cashier.staff'])
            ->where('_store',$request->store)
            ->whereYear('created_at',$year)
            ->whereMonth('created_at',$month)
            ->get();
        if($quiz->isEmpty()){
            return response()->json([
                'average'=>0,
                'recommend'=>['si'=>0,'no'=>0],
                'questions'=>[
                    'service'=>0,
                    'speed'=>0,
                    'info'=>0
                ],
                'comments'=>[],
                'sellers'=>[],
                'cashiers'=>[]
            ]);
        }
        $average = $quiz->avg(function($q){
            return ($q->second + $q->third + $q->fourth) / 3;
        });
        $si = $quiz->where('fifth','Si')->count();
        $no = $quiz->where('fifth','No')->count();
        $total = $quiz->count();
        $service = $quiz->avg('second');
        $speed   = $quiz->avg('third');
        $info    = $quiz->avg('fourth');
        $comments = $quiz
            ->whereNotNull('sixth')
            ->sortByDesc('created_at')
            ->take(10)
            ->values()
            ->map(fn($q)=>[
                'id'=>$q->id,
                'sixth'=>$q->sixth
            ]);
        $sellers = $quiz
            ->groupBy('_seller')
            ->map(function($items){

                $avg = $items->avg(function($q){
                    return ($q->second + $q->third + $q->fourth) / 3;
                });

                return [
                    'id'=>$items->first()->seller?->id,
                    'name'=>$items->first()->seller?->complete_name ?? 'N/A',
                    'score'=>round($avg,2)
                ];
            })
            ->sortByDesc('score')
            ->values();
        $cashiers = $quiz
            ->groupBy('_cashier')
            ->map(function($items){

                $avg = $items->avg(function($q){
                    return ($q->second + $q->third + $q->fourth) / 3;
                });

                return [
                    'id'=>$items->first()->cashier?->id,
                    'name'=>$items->first()->cashier?->staff?->complete_name ?? 'N/A',
                    'score'=>round($avg,2)
                ];
            })
            ->sortByDesc('score')
            ->values();
        return response()->json([
            'average'=>round($average,2),

            'recommend'=>[
                'si'=>round(($si/$total)*100),
                'no'=>round(($no/$total)*100)
            ],

            'questions'=>[
                'service'=>round($service,2),
                'speed'=>round($speed,2),
                'info'=>round($info,2)
            ],

            'comments'=>$comments,
            'sellers'=>$sellers,
            'cashiers'=>$cashiers,
            'total'=>$quiz->count()
        ]);
    }
}
