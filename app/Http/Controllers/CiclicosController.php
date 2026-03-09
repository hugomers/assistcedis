<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;
use App\Models\CycleCountVA;
use App\Models\CycleCountBodyVA;
use App\Models\ProductVA;
use App\Models\ProductCategoriesVA;
use App\Models\CellerVA;
use App\Models\Invoice;
use App\Models\CellerSectionVA;
use App\Models\AccountVA;
use App\Models\User;
use App\Models\Warehouses;

use App\Http\Resources\inventory as InventoryResource;

class CiclicosController extends Controller
{
    public function index(Request $request){//yasta
            $fechas = $request->date;
            if(isset($fechas['from'])){
                $from = $fechas['from'];
                $to = $fechas['to'];
            }else{
                $from = $fechas;
                $to = $fechas;
            }
            // return $storeA;
            $warehouse = $request->_warehouse;

            $inventories = CycleCountVA::with([ 'state', 'type', 'log', 'created_by', 'responsables','warehouse' ])
                ->withCount('products')
                ->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
                ->where("_warehouse",$warehouse)
                ->get();

            $inventories->each(function ($inv) {
                $total = 0;
                $count = 0;
                foreach ($inv->products as $p) {
                    $acc = $p->pivot->stock_acc;
                    $end = $p->pivot->stock_end;
                    if ($acc > 0 && $end > 0) {
                        $precision = ($acc / $end);
                        $total += $precision;
                    }
                    $count++;
                }
                $inv->precision = $count > 0 ? round($total / $count, 2) * 100 : 0;
            });
            $responsables = User::where([['_store', $request->sid()],['_state','!=',4]])->get();
            $seccion = ProductCategoriesVA::where('deep',0)->where('alias','!=',null)->get();
            // $cellers = Warehouses::with(['sections' => fn($q) => $q->whereNull('deleted_at')])->where('_store',$request->sid())->get();
            $cellers = CellerSectionVA::where('_warehouse',$warehouse)->whereNull('deleted_at')->get();

            return response ()->json([
                "inventories"=>$inventories,
                "colab" => $responsables,
                "secciones"=>$seccion,
                "locations"=>$cellers,
            ]);
    }

    public function find(Request $request){//yuasata
        $folio = $request->route("folio");
        $wkp =  $request->query("store");

        $inventory = CycleCountVA::with([
                        'warehouse',
                        'created_by',
                        'type',
                        'state',
                        'responsables',
                        'log',
                        'products' => function($query) use($wkp,$request){
                                            $query->with(['locations' => function($query) use($wkp){
                                                $query->whereHas('warehouse', function($query) use($wkp){
                                                    $query->where('id', $wkp);
                                                });
                                            },
                                        'stocks'=> function($q) use ($request){$q->where('_store',$request->sid());} ]);
                                        }
                    ])
                    ->where([ ["id","=",$folio], ["_warehouse",$wkp] ])
                    ->first();

            $total = 0;
            $count = 0;
            foreach ($inventory->products as $p) {
                $acc = $p->pivot->stock_acc;
                $end = $p->pivot->stock_end;
                if ($acc > 0 && $end > 0) {
                    $precision = ($acc / $end);
                    $total += $precision;
                }
                $count++;
            }
            $inventory->precision = $count > 0 ? round($total / $count, 2) * 100 : 0;
        if($inventory){
            return response()->json([
                "inventory" => $inventory,
                "params" => [$folio, $wkp]
            ]);
        }else{ return response("Not Found",404); }
    }

    public function secciones(){
        $seccion = ProductCategoriesVA::where('deep',0)->where('alias','!=',null)->get();
        return response()->json($seccion);
    }

    public function getProductsReport(){
        $families = ProductCategoriesVA::with('familia.seccion')->where([['alias','!=',null]])
        ->get();
        $products = ProductVA::with([
            'category.familia.seccion'
            ])
            ->select('*')
            ->selectSub(function ($query) {
                $query->from('product_stock')
                    ->selectRaw('SUM(gen) + SUM(exh) + SUM(fdt) +  SUM(in_transit)')
                    ->whereColumn('product_stock._product', 'products.id');
            }, 'total_stock')
            ->selectSub(function ($query) {
                $query->from('product_sold')
                    ->selectRaw('IF(SUM(product_sold.amount) IS NULL, 0 ,SUM(product_sold.amount)) ')
                    ->join('sales', 'product_sold._sale', '=', 'sales.id')
                    ->whereYear('sales.created_at', 2024)
                    ->whereColumn('product_sold._product', 'products.id');
            }, 'total_venta')->where('_status','!=',4)->get();

        $res = [
            'families'=>$families,
            'products'=>$products
        ];

        return response()->json($res);
    }

    public function getChangePrices(Request $request,$wid){
        $fechas = $request->fechas;
        // return $fechas;
        if(isset($fechas['from'])){
            $from = $fechas['from'];
            $to = $fechas['to'];
        }else{
            $from = $fechas;
            $to = $fechas;
        }
        $workpoint_from = $wid;
        $products = ProductVA::with([
        'category.familia.seccion',
        'prices'  => function($query){
            $query->whereIn('_type',[1,2,3,4])->distinct();
        },
        'locations'  => function($query) use($workpoint_from ) {
            $query->whereHas('celler', function($query)use($workpoint_from){
                $query->where('_workpoint', $workpoint_from );
            });
        },
        'stocks' => function($query) use ($workpoint_from) { //Se obtiene el stock de la sucursal
            $query->where('_workpoint',$workpoint_from)->distinct();
        }])
        ->where('_status','!=',4)
        ->whereBetween(DB::raw('DATE(updated_at)'),[$from,$to])
        ->get();
        return response($products,200);
    }

    public function getProductsCompare(Request $request){
        $sid = $request->route('sid');
        $seccion = $request->sections;
            $products = ProductVA::with([
                'category.familia.seccion',
                'stocks' => function($query) use ($sid) { //Se obtiene el stock de la sucursal
                    $query->whereIn('_workpoint',[1,2,$sid])->distinct();
                }
                ])
                ->withMax('purchases as ultll' , 'created_at')
                ->whereHas('category.familia.seccion', function($query) use ($seccion) { // Aplicamos el filtro en la relación seccion
                    $query->whereIn('name',$seccion);
                })
                ->whereHas('stocks', function($query) { // Solo productos con stock mayor a 0 en el workpoint
                    $query->whereIn('_workpoint', [1, 2])
                          ->where('stock', '>', 0); // Filtra solo aquellos con stock positivo
                })
                ->whereHas('stocks', function($query) use ($sid) { // Solo productos con stock mayor a 0 en el workpoint
                    $query->where('_workpoint',$sid)
                          ->where('stock', '=', 0); // Filtra solo aquellos con stock positivo
                })
                ->where('_status','!=',4)->get();
        return response()->json($products);
    }

    public function obtProductSections(Request $request){//YASTA
        $warehouse = $request->_warehouse;
        $sectionId = $request->ubicacion;
        $seccion = $request->seccion;
        $allIds = [];
        $sections = CellerSectionVA::with(['children' => fn($q) => $q->whereNull('deleted_at')])->whereIn('id',$sectionId)->get();
        if (!$sections) {
            return response()->json([], 404);
        }
        $allIds = [];

        foreach ($sections as $section) {
            $allIds = array_merge($allIds, $section->getAllDescendantIds());
        }

        $allIds = array_unique($allIds);
        $products = ProductVA::with([
            'units',
            'variants',
            'state',
            'category.familia.seccion',
            'locations' => function($query) use ($warehouse) {
                $query->with('warehouse')
                ->whereNull('deleted_at')
                ->whereHas('warehouse', function($query) use ($warehouse) {
                    $query->where([['id', $warehouse]]);
                });
            },
        ])
        ->whereHas('category.familia.seccion', function($query) use ($seccion) {
            $query->whereIn('id',$seccion);
        })
        ->whereHas('locations', function($q) use($allIds){ $q->whereIn('id',$allIds);})
        ->where('_state','!=',4)
        ->get();
        return response()->json($products);
    }

    public function obtProductSLocation(Request $request){
        $warehouse = $request->_warehouse;
        $seccion = $request->seccion;
        $productos = ProductVA::with([
            'providers',
            'makers',
            'stocks' => function($query) use ($warehouse) {
                $query->where([["_current", ">", 0], ["id", $warehouse]]);
            },
            'locations' => function($query) use ($warehouse) {
                $query->where('deleted_at',null)->whereHas('warehouse', function($query) use ($warehouse) {
                    $query->where('id', $warehouse);
                });
            },
            'category.familia.seccion',
            'state'
        ])
        ->whereHas('stocks', function($query) use ($warehouse) {
            $query->where([["_current", ">", 0], ["id", $warehouse]]);
        })
        ->whereHas('locations', function($query) use ($warehouse) {
            $query->where('deleted_at',null)->whereHas('warehouse', function($query) use ($warehouse) {
                $query->where('id', $warehouse);
        });},'<=',0)
        ->whereHas('category.familia.seccion', function($query) use ($seccion) {
            $query->whereIn('id',$seccion);
        })
        ->where('_state', '!=', 4)
        ->get();
        return response()->json($productos);
    }

    public function addCyclecount(Request $request){//yasta
        $_products = $request->collect('products')->pluck('id')->values()->all();
        $created_by = $request->uid();
        $warehouse = $request->_warehouse;
        if (empty($_products) || !$created_by) {
            return response()->json([
                'success' => false,
                'message' => 'Faltan productos o usuario creador'
            ], 422);
        }
            try {
                $result = DB::transaction(function() use ($request, $warehouse, $_products, $created_by) {
                    $counter = CycleCountVA::create([
                        'notes' => $request->notes ?? "",
                        '_warehouse' => $warehouse,
                        '_created_by' => $created_by,
                        '_type' => $request->type['val']['id'] ?? null,
                        '_state' => 1,
                        'settings'=>json_encode($request->all())
                    ]);

                    $this->log(1, $counter, $created_by);
                    $responsables = $request->collect('resgen')->pluck('id')->filter()->values()->all();

                    if (!empty($responsables)) {
                        $counter->responsables()->syncWithoutDetaching($responsables);
                    }

                    // traer productos con stocks y ubicaciones filtradas por workpoint
                    $products = ProductVA::with([
                        'stocks' => function($q) use ($counter) {
                            $q->where('_warehouse', $counter->_warehouse);
                        },
                        'locations' => function($q) use ($counter) {
                            $q->whereNull('deleted_at')->whereHas('warehouse', function($q2) use ($counter){
                                $q2->where('id', $counter->_warehouse);
                            });
                        }
                    ])->whereIn('id', $_products)->get();

                    // $products_add = [];

                    foreach ($products as $product) {
                        $stock = 0;
                        if ($product->relationLoaded('stocks') && $product->stocks->count() > 0) {
                                $stock = $product->stocks[0]->pivot->_current ?? 0;
                        }

                        $counter->products()->attach($product->id, [
                            'stock' => $stock,
                            'stock_acc' => $stock > 0 ? null : 0,
                            'details' => json_encode(["editor" => ""])
                        ]);

                        // $products_add[] = [
                        //     "id" => $product->id,
                        //     "code" => $product->code,
                        //     "short_code" => $product->short_code,
                        //     "description" => $product->description,
                        //     // "dimensions" => $product->dimensions,
                        //     "pieces" => $product->pieces,
                        //     "ordered" => [
                        //         "stocks" => $stock,
                        //         "stocks_acc" => $stock > 0 ? null : 0,
                        //         "details" => ["editor" => ""]
                        //     ],
                        //     "units" => $product->units,
                        //     "locations" => $product->locations->map(function($location){
                        //         return [
                        //             "id" => $location->id,
                        //             "name" => $location->name,
                        //             "alias" => $location->alias,
                        //             "path" => $location->path
                        //         ];
                        //     })->values()->all()
                        // ];
                    }

                    // $counter->settings = json_encode(["warehouse" => $warehouse]);
                    $counter->_state = 2;
                    $counter->save();
                    $this->log(2, $counter, $created_by);
                    return [
                        'counter' => $counter->fresh(['warehouse', 'created_by', 'type', 'state', 'responsables', 'log'])->loadCount('products') ,
                    ];
                }, 5);

                if ($result && isset($result['counter'])) {
                    $cyclecounts[] = [
                        'warehouse' => $warehouse,
                        'counter' => $result['counter'],
                    ];
                }
            } catch (\Throwable $e) {
                \Log::error('addCyclecount error for warehouse '.$warehouse.': '.$e->getMessage(), [
                    'exception' => $e
                ]);
                return response()->json([
                    'success' => false,
                    'message' => "Error al procesar warehouse {$warehouse}",
                    'error' => $e->getMessage()
                ], 500);
            }

        return response()->json([
            'success' => true,
            'data' => $cyclecounts
        ]);
    }

    public function log($case, CycleCountVA $inventory, $user){//yasta
        $account = User::find($user);
        $responsable = $account ? ($account->name . ' ' . ($account->surnames ?? '')) : 'Desconocido';

        switch($case){
            case 1:
                $inventory->log()->attach(1, [
                    '_user'=>$user,
                    'details' => json_encode(["responsable" => $responsable]),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                return true;

            case 2:
                if ($inventory->products()->count() > 0) {
                    $inventory->log()->attach(2, [
                         '_user'=>$user,
                        'details' => json_encode(["responsable" => $responsable]),
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    return true;
                }
                return false;

            case 3:
                $num = $inventory->products()->whereNull('stock_acc')->count();
                if ($num <= 0) {
                    $this->saveFinalStock($inventory);
                    $inventory->log()->attach(3, [
                         '_user'=>$user,
                        'details' => json_encode(["responsable" => $responsable]),
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    return true;
                }
                return false;

            case 4:
                $inventory->log()->attach(4, [
                     '_user'=>$user,
                    'details' => json_encode(["responsable" => $responsable]),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                return true;

            default:
                return false;
        }
    }

    public function saveFinalStock(CycleCountVA $inventory){
        // $workpoint = $inventory->_workpoint;
        // $warehouse = json_decode($inventory->settings)['warehouse']->id;

        $settings = json_decode($inventory->settings);
        $warehouse = $inventory->warehouse;
        $warehouseId = $warehouse->id;

        foreach($inventory->products as $product){
            $stock_store = $product->stocks->filter(function($stock) use ($warehouseId){
                return ($stock->id ?? $stock->_warehouse ?? null) == $warehouseId;
            })->values()->all();
            $stock = count($stock_store) > 0 ? ($stock_store[0]->pivot->_current ?? 0) : 0;
            $inventory->products()->updateExistingPivot($product->id, ["stock_end" => $stock]);
        }
    }

    public function getCyclecount(Request $request){//yasta
        $id = $request->cyclecount;
        $warehouse = $request->_warehouse;
        $uid = $request->uid();
        $user = User::with('rol')->find($request->uid());
        $rol = $user->_rol;
        $cyclecount = CycleCountVA::find($id);
        if($cyclecount){
            $cyclecount->load(['warehouse', 'created_by', 'type', 'state', 'responsables', 'log']);
            $cyclecount = $cyclecount->load(['products.locations' => function($query)use($warehouse){
                    $query->with('warehouse')->where('deleted_at',null)->whereHas('warehouse', function($query) use($warehouse) {
                        $query->where('id', $warehouse);
                    });
            },'products.variants']);
            if(in_array($rol, [1,2,5,6,12,22,18])){
                return response()->json($cyclecount,200);
            }else{
                $responsablesIds = $cyclecount->responsables->pluck('id')->toArray();

                if (in_array($uid, $responsablesIds)) {
                    return response()->json($cyclecount, 200);
                }
                return response()->json([
                    'message' => 'No autorizado para ver este ciclo'
                ], 403);
            }

        }else{
            return response()->json(['message'=>'El ciclico no existe :/'],404);
        }
    }

    public function saveValue(Request $request){ // Función para poner el valor contado durante el conteo ciclico
        $account = User::find($request->uid());
        $inventory = CycleCountVA::find($request->_inventory);
        if($inventory){
            $inventory->products()->updateExistingPivot($request->_product, ['stock_acc' => $request->stock , "details" => json_encode(["editor" => $account])]);
            return response()->json(["success" => true]);
        }
        return response()->json(["success" => false, "message" => "Folio de inventario no encontrado"]);
    }

    public function nextStep(Request $request){ // Función para cambiar el status de un conteo ciclico
        // $workpoint = $request->workpoint;
        $user = $request->uid();
        $inventory = CycleCountVA::find($request->_inventory);
        if($inventory){
            $status = isset($request->_state) ? $request->_state : $inventory->_state+1;
            if($status>0 && $status<4){
                $result = $this->log($status, $inventory,$user);
                if($result){
                    $inventory->_state= $status;
                    $inventory->save();
                    $inventory->load(['warehouse', 'created_by', 'type', 'state', 'responsables', 'log'])->loadCount('products');
                }
                return response()->json(["success" => $result, 'inventario' => $inventory]);
            }
            return response()->json(["success" => false, "message" => "Status no válido"]);
        }else{
            return response()->json(["success" => false, "message" => "Clave de inventario no válido"]);
        }
    }

    public function productCyclecount(Request $request){
        $params = $request->params;
        $warehouse = $request->_warehouse;
        $fechas = $params['date'];
        $sections = collect($params['sections'])->pluck('id');
        if(isset($fechas['from'])){
            $from = $fechas['from'];
            $to = $fechas['to'];
        }else{
            $from = $fechas;
            $to = $fechas;
        }
        $pasdat = [
            "from"=>$from,
            "to"=>$to,
            "warehouse"=>$warehouse
        ];

        $productosContados = CycleCountBodyVA::with('cyclecount')->whereHas('cyclecount', function($query) use ($pasdat) {
                $query->whereBetween(DB::raw('DATE(created_at)'), [$pasdat['from'], $pasdat['to']])
                ->where("_warehouse",$pasdat['warehouse']);
        })
        ->get()
        ->pluck('_product')
        ->unique();

        $productos = ProductVA::with([
        'providers',
        'makers',
        'stocks' => function($query) use ($warehouse){
            $query->where([["_current", ">", "0"], ["id", $warehouse]]);
        },
        'locations' => function($query) use ($warehouse){
            $query->where('deleted_at',null)->whereHas('warehouse', function($query) use ($warehouse){
                $query->where('id', $warehouse);
            });
        },
        'category.familia.seccion',
        'state'
        ])
        ->whereHas('stocks', function($query) use ($warehouse){
            $query->where([["_current", ">", 0], ["id", $warehouse]]);
            // ->orWhere([["exh", ">", 0], ["_workpoint", $store]]);
            })
        ->whereHas('category.familia.seccion', function($query) use ($sections) {
            $query->whereIn('id',$sections);
            })
        ->where('_state', '!=', 4)
        ->whereNotIn('id', $productosContados)
        ->get();
        // ->map(function($p){
        //         $p->bodega = $p->locations->filter(function($loc){
        //             return $loc->warehouse->_type == 1;
        //         })->values();

        //         $p->ventas = $p->locations->filter(function($loc){
        //             return $loc->warehouse->_type == 2;
        //         })->values();

        //         return $p;
        // });
        return response()->json($productos,200);
    }

    public function addMassiveProductCyclecount(Request $request){
        $imports = $request->all();
        $resproduct = [];
        foreach($imports as $import){
            $warehouse = $import['_warehouse'];
            $product = $import['_product'];

            $resproduct[] = ProductVA::with([
                'providers',
                'makers',
                'stocks' => function($query) use ($warehouse) {
                    $query->where([["_current", ">", 0], ["id", $warehouse]]);
                },
                'locations' => function($query) use ($warehouse) {
                    $query->with('warehouse')->where('deleted_at',null)->whereHas('warehouse', function($query) use ($warehouse) {
                        $query->where('id', $warehouse);
                    });
                },
                'category.familia.seccion',
                'state'
            ])
            // ->whereHas('variants', function(Builder $query) use ($product){
            //     $query->where('barcode', $product);
            // })
            ->orWhereHas('variants', function ($q) use ($id) {
                $query->where('code', $id)
                ->orWhere('barcode', $id);
            })
            ->orWhereHas('barcodes', function ($q) use ($id){
                $q->where('barcode',$id);
            })
            ->orWhere(function($query) use($product){
                $query->where('short_code', $product);
            })
            ->orWhere(function($query) use($product){
                $query->where('code', $product);
            })
            ->where('_state', '!=', 4)
            ->first();
        }

        return response()->json($resproduct);
    }

    public function presitionInventory(Request $request){
        $params = $request->params;
        $store = $request->sid();
        $fechas = $params['date'];
        if(isset($fechas['from'])){
            $from = $fechas['from'];
            $to = $fechas['to'];
        }else{
            $from = $fechas;
            $to = $fechas;
        }
        $pasdat = [
            "from"=>$from,
            "to"=>$to,
            "store"=>$store
        ];

        $products = ProductVA::with(['cyclecounts' => function($q) use ($pasdat) {
            $q->with('warehouse')->whereBetween(DB::raw('DATE(created_at)'), [$pasdat['from'], $pasdat['to']])
            ->whereHas('warehouse', function($query) use($pasdat){
                    $query->where([['_store',$pasdat['store']],['_type','!=',3]]);
            })
            ->where('_state',3);
            // ->latest('id');
        },
        'providers',
        'makers',
        'stocks' => function($query) use ($pasdat) {
            $query->where([["_store", $pasdat['store']]]);
        },
        'locations' => function($query) use ($pasdat) {
            $query->with('warehouse')->where('deleted_at',null)->whereHas('warehouse', function($query) use ($pasdat) {
                $query->where('_store', $pasdat['store']);
            });
        },
        'category.familia.seccion',
        'state'
        ])
        ->whereHas('cyclecounts', function($q) use ($pasdat){
            $q->whereBetween(DB::raw('DATE(created_at)'), [$pasdat['from'], $pasdat['to']])
            ->whereHas('warehouse', function($query) use($pasdat){
                    $query->where([['_store',$pasdat['store']],['_type','!=',3]]);
            })
            ->where('_state',3);
        })
        ->get();
        $products->each(function($product) {
            $product->lastCounts = $product->cyclecounts
                ->groupBy('_warehouse')
                ->map(function ($counts) {
                    return $counts->sortByDesc('created_at')->first();
                })
                ->values();
        });
        return response()->json($products,200);
    }


}
