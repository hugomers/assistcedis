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
                'questions'=>[],
                'sellers'=>[],
                'cashiers'=>[],
                'total'=>0
            ]);
        }
        $total = $quiz->count();
        $scoreQuestions = [
            'first',
            'second',
            'third',
            'fourth',
            'fifth',
            'sixth',
            'seventh'
        ];
        $average = $quiz->avg(function($q) use ($scoreQuestions){
            return collect($scoreQuestions)->avg(fn($field) => $q->$field);
        });
        $si = $quiz->where('eightth','Si')->count();
        $no = $quiz->where('eightth','No')->count();
        $questions = collect($scoreQuestions)
            ->mapWithKeys(fn($q)=>[
                $q => round($quiz->avg($q),2)
            ]);
        $sellers = $quiz
            ->groupBy('_seller')
            ->map(function($items) use ($scoreQuestions){

                $avg = $items->avg(function($q) use ($scoreQuestions){
                    return collect($scoreQuestions)->avg(fn($f)=>$q->$f);
                });

                return [
                    'id'=>$items->first()->seller?->id,
                    'name'=>$items->first()->seller?->complete_name ?? 'Sin Nombre',
                    'score'=>round($avg,2)
                ];
            })
            ->sortByDesc('score')
            ->values();
        $cashiers = $quiz
            ->groupBy('_cashier')
            ->map(function($items) use ($scoreQuestions){

                $avg = $items->avg(function($q) use ($scoreQuestions){
                    return collect($scoreQuestions)->avg(fn($f)=>$q->$f);
                });
                return [
                    'id'=>$items->first()->cashier?->id,
                    'name'=>$items->first()->cashier?->staff?->complete_name ?? 'Sin Nombre',
                    'score'=>round($avg,2)
                ];
            })
            ->sortByDesc('score')
            ->values();
            $comments = $quiz
            ->where('eightth','No')
            ->whereNotNull('eightthno')
            ->where('eightthno','!=','')
            ->take(10)
            ->values()
            ->map(fn($q)=>[
                'id' => $q->id,
                'comment' => $q->eightthno
            ]);
        return response()->json([
            'average'=>round($average,2),
            'recommend'=>[
                'si'=> $total ? round(($si/$total)*100) : 0,
                'no'=> $total ? round(($no/$total)*100) : 0
            ],
            'questions'=>$questions,
            'sellers'=>$sellers,
            'cashiers'=>$cashiers,
            'comments'=>$comments,
            'total'=>$total
        ]);
    }
}
