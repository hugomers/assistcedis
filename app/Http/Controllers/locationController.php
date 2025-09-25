<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Staff;
use App\Models\Opening;
use App\Models\OpeningType;
use App\Models\Stores;
use Illuminate\Support\Facades\Http;
use App\Models\ClientVA;
use App\Models\WorkpointVA;
use App\Models\CellerVA;
use App\Models\CellerSectionVA;
use App\Models\ProductVA;




class locationController extends Controller
{
    public function index($sid){
        $cellers = CellerVA::with(['sections' => fn($q) => $q->whereNull('deleted_at')])->where('_workpoint',$sid)->get();
        return response()->json($cellers,200);
    }

    // public function obtProductSections(Request $request){
    //     $sectionId = $request->section;
    //     $section = CellerSectionVA::with('children')->find($sectionId);
    //     if (!$section) {
    //         return response()->json([], 404);
    //     }
    //     $allIds = $section->getAllDescendantIds();
    //     // return $allIds;
    //     $products = ProductVA::where('_status','!=',4)->whereHas('locations', function($q) use($allIds){ $q->whereIn('id',$allIds);})->get();
    //     return response()->json($products);
    // }


    public function obtProductSections(Request $request){
        $update = $request->update;
        $workpoint = $request->workpoint;
        $sectionId = $request->section;
        $section = CellerSectionVA::with(['children' => fn($q) => $q->whereNull('deleted_at')])->find($sectionId);
        if (!$section) {
            return response()->json([], 404);
        }
        $allIds = $section->getAllDescendantIds();

        $products = ProductVA::with([
            'prices' => function($query){
                $query->whereIn('_type', [1,2,3,4])->orderBy('_type');
            },
            'units',
            'variants',
            'status',
            'locations' => function($query) use ($workpoint) {
                $query->whereHas('celler', function($query) use ($workpoint) {
                $query->where([['_workpoint', $workpoint],['_type',2]]);
            });},
            'historicPrices' => function($q) {$q->latest('created_at')->limit(1);}
        ]);
        if($update){
            $products = $products->whereDate('updated_at',now()->format('Y-m-d'));
        }
        $products = $products->where('_status','!=',4)
        ->whereHas('locations', function($q) use($allIds){ $q->whereIn('id',$allIds);})->get();

        return response()->json($products);
    }

    public function obtSections(Request $request){
        $sectionId = $request->section;
        $section = CellerSectionVA::with(['children' => fn($q) => $q->whereNull('deleted_at')])->find($sectionId);
        return response()->json($section,200);
    }

}
