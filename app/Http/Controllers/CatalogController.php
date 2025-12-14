<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use App\Models\ProductVA;
use App\Models\PrinterVA;
use App\Models\ProvidersVA;
use App\Models\ProductStockVA;
use App\Models\MakersVA;
use App\Models\ProductCategoriesVA;
use App\Models\ProductUnitVA;
use App\Models\Stores;
use App\Models\User;
use App\Models\WorkpointVA;
use App\Models\AccountVA;
use App\Models\ControlFigures;
use App\Models\historyPricesVA;
class CatalogController extends Controller
{
    public function Index(){
        return response()->json(ProductCategoriesVA::where([['alias','!=',null],['deep',0]])->get(),200);
    }

    public function getPrinters(Request $request){
        $store = $request->all();
        $printers = PrinterVA::where([['_type',1],['_workpoint',$store['id_viz']]])->get();
        $users = AccountVA::where([['_wp_principal',$store['id_viz']],['id_tpv','!=',null]])->get();
        $res = [
            "printers"=>$printers,
            "users"=>$users
        ];
        return response()->json($res,200);
    }

    public function getFamilys($root){
        $getsection = ProductCategoriesVA::find($root);
        $getsection->children = ProductCategoriesVA::where([['root',$root]])->get();
        return response()->json($getsection,200);
    }

    public function getFamilysProducts(Request $request){
        $family = $request->family;
        $workpoint = $request->workpoint;
        $getsection = ProductCategoriesVA::find($family);
        $getsection->products = ProductVA::with([
        'stocks' => function($query) use ($workpoint){
            $query->whereIn("_workpoint", [1,2,$workpoint]);
        },
        'prices' => function($query){
            $query->whereIn('_type', [1, 2, 3, 4])->orderBy('id');
        },
        'category.familia.seccion',
        'status'
         ])->where('_status', '!=', 4)
         ->whereHas('category.familia', function($query) use ($family) {
            $query->where('id',$family);
         })
         ->get();
         $categories = ProductCategoriesVA::where([['root',$family]])->get();
         $res = [
            "family"=>$getsection,
            "categories"=>$categories
         ];
        return response()->json($res,200);
    }

    public function getProductMedia(Request $request){
        $product = $request->id;

        $files = Storage::files("vhelpers/Products/$product");

        $media = collect($files)->map(fn ($file) => [
            'type' => str_ends_with($file, '.mp4') ? 'video' : 'image',
            'url' => Storage::disk('s3')->url($file),
        ]);

        return response()->json($media);

    }
}
