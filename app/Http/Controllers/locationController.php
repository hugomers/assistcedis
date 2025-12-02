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
use App\Models\AccountVA;
use App\Models\CellerLogVA;
use App\Models\ProductCategoriesVA;
use Carbon\Carbon;





class locationController extends Controller
{
    public function index(Request $request){
        $rol = $request->_rol;
        $sid = $request->_workpoint;
        $cellers = CellerVA::with(['sections' => fn($q) => $q->whereNull('deleted_at')])->where('_workpoint',$sid);
        if(in_array($rol, [1,2,5,6,12,22,18])){//admins
            $cellers = $cellers->get();
        }else if(in_array($rol, [24,4,17,15,16,20])){//almacen
            $cellers = $cellers->where('_type',1)->get();
        }else if(in_array($rol, [8,9,27,28])){//ventas
            $cellers = $cellers->where('_type',2)->get();
        }
        return response()->json($cellers,200);
    }

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
                $query->whereNull('deleted_at');
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

    public function insertSection(Request $request){
        $sections = $request->sections;
        $create = [];
        foreach($sections as &$section){
            $section['details'] = json_encode($section['details']);
            $created[] = CellerSectionVA::create($section);
        }
        return response()->json($created);
    }

    public function addLocations(Request $request){
        $ip = $request->ip();
        $user = AccountVA::find($request->id_viz);
        $product = ProductVA::find($request->_product);
        if($product && !is_null($request->_location)){
            $changes = $product->locations()->toggle($request->_location);
            $details = [
                "user"=>$user,
                "ip"=>$ip,
                "type"=>null,
                "product"=>$request->_product,
                "section"=>null,
                "created" => Carbon::now()->format('Y-m-d'),
                "hora"    => Carbon::now()->format('H:i:s'),
            ];
            if (count($changes['attached'])>0) {
                $details['section']=$request->_location;
                $details['type']='Add';
            }
            if (count($changes['detached'])>0) {
                $details['section']=$request->_location;
                $details['type']='delete';
            }
            $celler = CellerSectionVA::where('id',$request->_location)->first();
            $log = new CellerLogVA;
            $log->details = json_encode($details);
            $log->_celler = $celler->_celler;
            $log->save();
            return response()->json([
                'success' => $changes
            ]);
        }else{
            return response()->json([
                'success' => false
            ],404);
        }
    }

    public function deleteSection(Request $request){//ok
        $ip = $request->ip();
        $user = AccountVA::find($request->id_viz);
        $section = CellerSectionVA::findOrFail($request->id);
        $ids = $section->getAllDescendantIds();
        CellerSectionVA::whereIn('id', $ids)
            ->update(['deleted_at' => Carbon::now()]);

        $sections = CellerSectionVA::whereIn('id', $ids)->get();

        foreach ($sections as $sec) {
            $details = [
                "user" => $user,
                "ip"   => $ip,
                "type" => "delete-section",
                "section" => $sec->id,
                "created" => Carbon::now()->format('Y-m-d'),
                "hora"    => Carbon::now()->format('H:i:s'),
            ];

            CellerLogVA::create([
                'details' => json_encode($details),
                '_celler' => $sec->_celler
            ]);
        }


        return response()->json([
            'message' => 'Secciones eliminadas correctamente',
            'ids' => $ids
        ]);
    }//falta log

    public function getInit($sid){
        $cellers = CellerVA::with(['sections' => fn($q) => $q->whereNull('deleted_at')])->where('_workpoint',$sid)->get();
        $categories = ProductCategoriesVA::whereNotNull('alias')->get();
        return response()->json([
            "celler"=>$cellers,
            "categories"=>$categories
        ],200);
    }

    public function obtProduct(Request $request){
        $workpoint = $request->workpoint;
        $sectionId = $request->section;
        $section = CellerSectionVA::with(['children' => fn($q) => $q->whereNull('deleted_at')])->find($sectionId);
        if (!$section) {
            return response()->json([], 404);
        }
        $allIds = $section->getAllDescendantIds();

        $products = ProductVA::with([
            'locations' => function($query) use ($workpoint) {
                $query->whereNull('deleted_at');
                $query->whereHas('celler', function($query) use ($workpoint) {
                    $query->where([['_workpoint', $workpoint]]);
            });},
        ])
        ->where('_status','!=',4)
        ->whereHas('locations', function($q) use($allIds){ $q->whereIn('id',$allIds);})
        ->get();
        return response()->json($products);
    }

    public function obtProductCategories(Request $request){
        $filters = $request->all();
        $products = ProductVA::with([
            'locations' => function($query) use ($filters) {
                $query->whereNull('deleted_at');
                $query->whereHas('celler', function($query) use ($filters) {
                    $query->where([['_workpoint', $filters['workpoint']]]);
            });},
            ])
            ->when(count($filters['categories']) > 0, fn($q) =>
                $q->whereHas('category', fn($q2) =>
                $q2->whereIn('id', $filters['categories'])))
            ->when(count($filters['familys'])> 0, fn($q) =>
                $q->whereHas('category.familia', fn($q2) =>
                $q2->whereIn('id', $filters['familys'])))
            ->when(count($filters['sections'])> 0, fn($q) =>
                $q->whereHas('category.familia.seccion', fn($q2) =>
                $q2->whereIn('id', $filters['sections'])))
            ->whereHas('locations', function($query) use ($filters) {
                $query->whereNull('deleted_at');
                $query->whereHas('celler', function($query) use ($filters) {
                    $query->where([['_workpoint', $filters['workpoint']]]);
            });})
            ->where('_status','!=',4)->get();
        return response()->json($products);
    }

    public function deleteSectionProducts(Request $request){//ok
        $ip = $request->ip();
        $user = AccountVA::find($request->id_viz);
        $productsIds = $request->products;
        $sectionId = $request->section;
        $section = CellerSectionVA::with(['children' => fn($q) => $q->whereNull('deleted_at')])->find($sectionId);
        if (!$section) {
            return response()->json([], 404);
        }
        $allIds = $section->getAllDescendantIds();
        $products = ProductVA::whereIn('id', $productsIds)->get();

        foreach ($products as $product) {
            $deleted = $product->locations()->wherePivotIn('_location', $allIds)->detach();
            if ($deleted > 0) {
                $details = [
                    "user" => $user,
                    "ip"   => $ip,
                    "type" => "delete",
                    "product" => $product->id,
                    "section" => $allIds,
                    "created" => Carbon::now()->format('Y-m-d'),
                    "hora"    => Carbon::now()->format('H:i:s'),
                ];

                $log = new CellerLogVA;
                $log->details = json_encode($details);
                $log->_celler = $section->_celler;
                $log->save();
            }
        }
        return response()->json([
            'success' => true,
            'deleted_products' => $products->pluck('id')
        ]);
    }

    public function deleteCategoriesLocations(Request $request){
        $ip = $request->ip();
        $products = $request->products;
        $user = AccountVA::find($request->id_viz);
        foreach ($products as $product) {
            $delProd = ProductVA::find($product['product']);
            $deleted = $delProd->locations()->wherePivotIn('_location', $product['locations'])->detach();
            if ($deleted > 0) {
                $celler = CellerSectionVA::whereIn('id',$product['locations'])->first();
                $details = [
                    "user" => $user,
                    "ip"   => $ip,
                    "type" => "delete",
                    "product" => $delProd->id,
                    "section" => $product['locations'],
                    "created" => Carbon::now()->format('Y-m-d'),
                    "hora"    => Carbon::now()->format('H:i:s'),
                ];

                $log = new CellerLogVA;
                $log->details = json_encode($details);
                $log->_celler = $celler->_celler; // si existe en RF
                $log->save();
            }
        }
        return response()->json([
            'success' => true,
            'deleted_products' => count($products)
        ]);
    }

    public function addMassiveLocation(Request $request){
        $ip = $request->ip();
        $res = [
            "goals"=>[],
            "fails"=>[]
        ];
        $products = $request->products;
        $user = AccountVA::find($request->id_viz);
        foreach ($products as $product) {
            $addLocation = ProductVA::where('code',$product['_product'])->first();
            if($addLocation){
                $validLocations = CellerSectionVA::whereIn('id', $product['_location'])
                    ->whereHas('celler', function($query) use ($product) {
                        $query->where('_workpoint', $product['_workpoint']);
                    })
                    ->pluck('id');
                    // return $validLocations;

                if ($validLocations->isNotEmpty()) {

                    $product['_location'] = $validLocations->toArray();
                    $addLocation->locations()->syncWithoutDetaching($product['_location']);
                    $locations = CellerSectionVA::whereIn('id', $product['_location'])
                        ->select('id','name','alias','path','_celler')
                        ->get();


                    $details = [
                        "user" => $user,
                        "ip"   => $ip,
                        "type" => "Add",
                        "product" => $addLocation->id,
                        "section" => $validLocations,
                        "created" => Carbon::now()->format('Y-m-d'),
                        "hora"    => Carbon::now()->format('H:i:s'),
                    ];

                    $log = new CellerLogVA;
                    $log->details = json_encode($details);
                    $log->_celler = $locations->first()->_celler ?? null;
                    $log->save();



                    $res['goals'][] = [
                        "product" => $product['_product'],
                        "success" => true,
                        "locations" => $locations,
                    ];
                } else {
                    $res['fails'][] = [
                        "product" => $product['_product'],
                        "success" => false,
                        "error" => 'La ubicacion no pertenece a tu sucursal',
                    ];
                }
            }else{
                $res['fails'][] = [
                    "product" => $product['_product'],
                    "success" => false,
                    "error" => 'El producto No Existe',
                ];
            }
        }
        return response()->json($res,200);
    }

    public function deleteMassiveLocation(Request $request){
        $ip = $request->ip();
        $products = $request->products;
        $user = AccountVA::find($request->id_viz);
        $res = [];

        foreach ($products as $product) {
            $delProd = ProductVA::where('code', $product['_product'])->first();

            if ($delProd) {
                $locationIds = $delProd->locations()
                    ->whereHas('celler', function($query) use ($product) {
                        $query->where('_workpoint', $product['_workpoint']);
                    })
                    ->pluck('id');
                if ($locationIds->isNotEmpty()) {
                    $delProd->locations()->detach($locationIds);
                    $celler = CellerSectionVA::whereIn('id',$locationIds)->first();
                    $details = [
                        "user" => $user,
                        "ip"   => $ip,
                        "type" => "delete",
                        "product" => $$delProd->id,
                        "section" => $locationIds,
                        "created" => Carbon::now()->format('Y-m-d'),
                        "hora"    => Carbon::now()->format('H:i:s'),
                    ];

                    $log = new CellerLogVA;
                    $log->details = json_encode($details);
                    $log->_celler = $celler->_celler;
                    $log->save();

                    $res[] = [
                        'Producto' => $product['_product'],
                        'resultado' => 'OK',
                        'Mensaje' => ''
                    ];
                } else {
                    $res[] = [
                        'Producto' => $product['_product'],
                        'resultado' => ':0',
                        'Mensaje' => 'No hay locaciones de esta sucursal para eliminar'
                    ];
                }
            } else {
                $res[] = [
                    'Producto' => $product['_product'],
                    'resultado' => ':0',
                    'Mensaje' => 'Producto no existe'
                ];
            }
        }
        return response()->json($res, 200);
    }

    public function reportLocations (Request $request){
        $workpoint = $request->workpoint;
        $seccion = $request->section;
        $rol = $request->_rol;
        $type = $request->_type;
        $data = [
            "workpoint"=>$workpoint,
            "rol"=>$rol
        ];

        $productos = ProductVA::with([
        'category.familia.seccion',
        'status',
        'providers',
        'makers',
        ])
        ->whereHas('category.familia.seccion', function($query) use ($seccion) {
            $query->where('id',$seccion);
        })
        ->where('_status', '!=', 4);

        if($type == 1){//con stock sin ubicacion
            $productos = $productos->with([
                'stocks' => function($query) use ($workpoint) {
                    $query->where([["gen", ">", 0], ["_workpoint", $workpoint]])
                    ->orWhere([["exh", ">", 0], ["_workpoint", $workpoint]]);
                },
                'locations' => function($query) use ($data) {
                    $query->where('deleted_at',null)->whereHas('celler', function($query) use ($data) {
                        $rol = $data['rol'];
                        $query->where('_workpoint', $data['workpoint']);
                        if(in_array($rol, [1,2,5,6,12,22,18])){//admins
                            $query = $query;
                        }else if(in_array($rol, [24,4,17,15,16,20])){//almacen
                            $query = $query->where('_type',1);
                        }else if(in_array($rol, [8,9,27,28])){//ventas
                            $query = $query->where('_type',2);
                        }
                    });
                },
            ])
            ->whereHas('stocks', function($query) use ($workpoint) {
                $query->where([["gen", ">", 0], ["_workpoint", $workpoint]])
                ->orWhere([["exh", ">", 0], ["_workpoint", $workpoint]]);
            })
            ->whereHas('locations', function($query) use ($data) {
                $query->where('deleted_at',null)->whereHas('celler', function($query) use ($data) {
                    $rol = $data['rol'];
                    $query->where('_workpoint', $data['workpoint']);
                    if(in_array($rol, [1,2,5,6,12,22,18])){//admins
                        $query = $query;
                    }else if(in_array($rol, [24,4,17,15,16,20])){//almacen
                        $query = $query->where('_type',1);
                    }else if(in_array($rol, [8,9,27,28])){//ventas
                        $query = $query->where('_type',2);
                    }
            });},'<=',0)->get();
        }else if($type == 2){//sin stock con ubicacion

            $productos = $productos->with([
                'stocks' => function($query) use($workpoint) {
                    $query->where([["stock", "<=", "0"], ["_workpoint", $workpoint]]);
                },
                'locations' => function($query)use($data){
                    $query->where('deleted_at',null)->whereHas('celler', function($query) use($data) {
                        $rol = $data['rol'];
                        $query->where('_workpoint', $data['workpoint']);
                        if(in_array($rol, [1,2,5,6,12,22,18])){//admins
                            $query = $query;
                        }else if(in_array($rol, [24,4,17,15,16,20])){//almacen
                            $query = $query->where('_type',1);
                        }else if(in_array($rol, [8,9,27,28])){//ventas
                            $query = $query->where('_type',2);
                        }
                    });
                },
            ])
            ->whereHas('stocks', function($query) use($workpoint) {
                $query->where([["stock", "<=", 0], ["stock", "<=", 0], ["_workpoint", $workpoint]]);
            })
            ->whereHas('locations', function($query) use($data) {
                $query->where('deleted_at',null)->whereHas('celler', function($query) use($data) {
                    $rol = $data['rol'];
                    $query->where('_workpoint', $data['workpoint']);
                    if(in_array($rol, [1,2,5,6,12,22,18])){//admins
                        $query = $query;
                    }else if(in_array($rol, [24,4,17,15,16,20])){//almacen
                        $query = $query->where('_type',1);
                    }else if(in_array($rol, [8,9,27,28])){//ventas
                        $query = $query->where('_type',2);
                    }
                });},'>',0)
            ->get();
        }else if($type == 3){//con stock con ubicacion

            $productos = $productos->with([
                'stocks' => function($query) use($workpoint) {
                    $query->where([["stock", ">", "0"], ["_workpoint", $workpoint]]);
                },
                'locations' => function($query)use($data){
                    $query->where('deleted_at',null)->whereHas('celler', function($query) use($data) {
                        $rol = $data['rol'];
                        $query->where('_workpoint', $data['workpoint']);
                        if(in_array($rol, [1,2,5,6,12,22,18])){//admins
                            $query = $query;
                        }else if(in_array($rol, [24,4,17,15,16,20])){//almacen
                            $query = $query->where('_type',1);
                        }else if(in_array($rol, [8,9,27,28])){//ventas
                            $query = $query->where('_type',2);
                        }
                    });
                },
            ])
            ->whereHas('stocks', function($query) use($workpoint) {
                $query->where([["stock", ">", 0], ["stock", ">", 0], ["_workpoint", $workpoint]]);
            })
            ->whereHas('locations', function($query) use($data) {
                $query->where('deleted_at',null)->whereHas('celler', function($query) use($data) {
                    $rol = $data['rol'];
                    $query->where('_workpoint', $data['workpoint']);
                    if(in_array($rol, [1,2,5,6,12,22,18])){//admins
                        $query = $query;
                    }else if(in_array($rol, [24,4,17,15,16,20])){//almacen
                        $query = $query->where('_type',1);
                    }else if(in_array($rol, [8,9,27,28])){//ventas
                        $query = $query->where('_type',2);
                    }
                });},'>',0)
            ->get();
        }
        return response()->json($productos,200);
    }

    public function reportMinMax(Request $request){
        $workpoint = $request->workpoint;
        $seccion = $request->section;
        $rol = $request->_rol;
        $type = $request->_type;
        $data = [
            "workpoint"=>$workpoint,
            "rol"=>$rol
        ];

        $productos = ProductVA::with([
        'category.familia.seccion',
        'status',
        'providers',
        'makers',
        'locations' => function($query)use($data){
            $query->where('deleted_at',null)->whereHas('celler', function($query) use($data) {
                $rol = $data['rol'];
                $query->where('_workpoint', $data['workpoint']);
                if(in_array($rol, [1,2,5,6,12,22,18])){//admins
                    $query = $query;
                }else if(in_array($rol, [24,4,17,15,16,20])){//almacen
                    $query = $query->where('_type',1);
                }else if(in_array($rol, [8,9,27,28])){//ventas
                    $query = $query->where('_type',2);
                }
            });
        },
        ])
        ->whereHas('category.familia.seccion', function($query) use ($seccion) {
            $query->where('id',$seccion);
        })
        ->where('_status', '!=', 4);

        if($type == 1){// con maximos con stock
            $productos = $productos->with(["stocks" => function($query) use ($workpoint) {
                $query->where([["stock", ">", 0], ["min", ">", 0], ["max", ">", 0], ["_workpoint", $workpoint]]
            );}])
            ->whereHas('stocks', function($query) use ($workpoint) {
                $query->where([["stock", ">", 0], ["min", ">", 0], ["max", ">", 0], ["_workpoint", $workpoint]]);
            })->get();
        }else if($type == 2){ // sin maximos con stock
            $productos = $productos->with(["stocks" => function($query) use ($workpoint) {
                $query->where([["stock", ">", 0], ["min", "<=", 0], ["max", "<=", 0], ["_workpoint", $workpoint]]
            );}])
            ->whereHas('stocks', function($query) use ($workpoint) {
                $query->where([["stock", ">", 0], ["min", "<=", 0], ["max", "<=", 0], ["_workpoint", $workpoint]]);
            })->get();
        }else if($type == 3){// con maximos sin stock
            $productos = $productos->with(["stocks" => function($query) use ($workpoint) {
                $query->where([["stock", "<=", 0], ["min", ">", 0], ["max", ">", 0], ["_workpoint", $workpoint]]
            );}])
            ->whereHas('stocks', function($query) use ($workpoint) {
                $query->where([["stock", "<=", 0], ["min", ">", 0], ["max", ">", 0], ["_workpoint", $workpoint]]);
            })->get();
        }
        return response()->json($productos,200);
    }

}
