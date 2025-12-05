<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;
use App\Models\CycleCountVA;
use App\Models\ProductVA;
use App\Models\ProductCategoriesVA;
use App\Models\CellerVA;
use App\Models\Invoice;
use App\Models\CellerSectionVA;
use App\Models\AccountVA;
use App\Models\User;
use App\Http\Resources\inventory as InventoryResource;

class CiclicosController extends Controller
{
    public function index(Request $request){
            $fechas = $request->date;
            if(isset($fechas['from'])){
                $from = $fechas['from'];
                $to = $fechas['to'];
            }else{
                $from = $fechas;
                $to = $fechas;
            }
            $storeA = $request->suc;
            // return $storeA;
            $store = $request->store;

            $inventories = CycleCountVA::with([ 'status', 'type', 'log', 'created_by', 'responsables' ])
                ->withCount('products')
                ->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
                ->where("_workpoint",$store)
                ->get();
            $responsables = User::with('staff')->where('_store', $storeA)->WhereIn('_rol',[4,8,9,24])->get();
            $seccion = ProductCategoriesVA::where('deep',0)->where('alias','!=',null)->get();
            $cellers = CellerVA::with(['sections' => fn($q) => $q->whereNull('deleted_at')])->where('_workpoint',$store)->get();

            return response ()->json([
                "inventories"=>$inventories,
                "colab" => $responsables,
                "secciones"=>$seccion,
                "locations"=>$cellers,
            ]);
    }

    public function find(Request $request){
        $folio = $request->route("folio");
        $wkp = $request->query("store");

        $inventory = CycleCountVA::with([
                        'workpoint',
                        'created_by',
                        'type',
                        'status',
                        'responsables',
                        'log',
                        'products' => function($query) use($wkp){
                                            $query->with(['locations' => function($query) use($wkp){
                                                $query->whereHas('celler', function($query) use($wkp){
                                                    $query->where('_workpoint', $wkp);
                                                });
                                            }]);
                                        }
                    ])
                    ->where([ ["id","=",$folio], ["_workpoint","=",$wkp] ])
                    ->first();

        if($inventory){
            return response()->json([
                "inventory" => new InventoryResource($inventory),
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

    public function getProducts(Request $request){ // Función autocomplete 2.0
        $workpoint = $request->_workpoint;
        // return 'hjos';
        //Se obtienen todo los datos del producto y se le agrega la sección, familia y categoría
        // $query = Product::query()->selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS categoryy');
        $query = ProductVA::with(['category.familia.seccion','prices']);

        if(isset($request->autocomplete) && $request->autocomplete){ //Valida si se utilizara la función de autocompletado ?
            $codes = explode('ID-', $request->autocomplete); // Si el codigo tiene ID- al inicio la busqueda sera por el id que se le asigno en el catalog maestro (tabla products)
            if(count($codes)>1){
                $query = $query->where('id', $codes[1]);
            }elseif(isset($request->strict) && $request->strict){ //La coincidencia de la busqueda sera exacta
                /*
                    La busqueda se realiza por:
                    Modelo -> code
                    Código -> name
                    Codigo de barras -> barcode
                    Códigos relacionados -> variants.barcode
                */
                if(strlen($request->autocomplete)>1){
                    $query = $query->whereHas('variants', function(Builder $query) use ($request){
                        $query->where('barcode', $request->autocomplete);
                    })
                    ->orWhere(function($query) use($request){
                        $query->orWhere('name', $request->autocomplete)
                        ->orWhere('barcode', $request->autocomplete)
                        ->orWhere('code', $request->autocomplete);
                    });
                }
            }else{ //La busqueda se realizara por similitud
                /*
                    La busqueda se realiza por:
                    Modelo -> code
                    Código -> name
                    Codigo de barras -> barcode
                    Códigos relacionados -> variants.barcode
                */
                if(strlen($request->autocomplete)>1){
                    $query = $query->whereHas('variants', function(Builder $query) use ($request){
                        $query->where('barcode', 'like', '%'.$request->autocomplete.'%');
                    })
                    ->orWhere(function($query) use($request){
                        $query->orWhere('name', $request->autocomplete)
                        ->orWhere('barcode', $request->autocomplete)
                        ->orWhere('code', $request->autocomplete)
                        ->orWhere('name', 'like','%'.$request->autocomplete.'%')
                        ->orWhere('code', 'like','%'.$request->autocomplete.'%');
                    });
                }
            }
        }
        $query = $query->where("_status", "!=", 4);

        // if(!in_array($this->account->_rol, [1,2,3,8])){
        //     /*
        //         Solo las personas que tengan un rol administrativo podran
        //         visualizar todo el catalogo de productos.
        //         Si no tienes un rol administrativo solo veras los productos vigenten
        //         en el catalogo de factusol CEDIS
        //      */
        //     $query = $query->where("_status", "!=", 4);
        // }

        if(isset($request->products) && $request->products){ //Se puede buscar mas de un codigo a la vez mendiente el parametro products
            $query = $query->whereHas('variants', function(Builder $query) use ($request){
                $query->whereIn('barcode', $request->products);
            })
            ->orWhereIn('name', $request->products)
            ->orWhereIn('code', $request->product);
        }

        if(isset($request->_category)){ //Se puede realizar una busqueda con el filtro de sección, familia, categoría mediente el ID de lo que estamos buscando
            $_categories = $this->getCategoriesChildren($request->_category); // Se obtiene los hijos de esa categoría
            $query = $query->whereIn('_category', $_categories); // Se añade el filtro de la categoría para realizar la busqueda
        }

        if(isset($request->_status)){ // Se puede realizar una busqueda con el filtro de status del producto mediante el ID del status que estamos buscando
            $query = $query->where('_status', $request->_status); // Se añade el filtro de la categoría para realizar la busqueda
        }

        if(isset($request->_location)){ //Se puede realizar una busqueda con filtro de ubicación del producto mediante el ID de la ubicación (sección, pasillo, tarima, etc) que estamos buscando
            $_locations = $this->getSectionsChildren($request->_location); //Se obtienen todos los hijos de la sección de la busqueda para realizar la busqueda completa
            $query = $query->whereHas('locations', function( Builder $query) use($_locations){
                $query->whereIn('_location', $_locations); // Se añade el filtro de la sección para realizar la busqueda
            });
        }

        if(isset($request->_celler) && $request->_celler){ // Se puede realizar una busqueda con filtro de almacen
            $locations = \App\CellerSection::where([['_celler', $request->_celler],['deep', 0]])->get(); // Se obtiene todas las ubicaciones dentro del almacen
            $ids = $locations->map(function($location){
                return $this->getSectionsChildren($location->id);
            });
            $_locations = array_merge(...$ids); // Se genera un arreglo con solo los ids de las ubicaciones
            $query = $query->whereHas('locations', function( Builder $query) use($_locations){
                $query->whereIn('_location', $_locations);
            });
        }

        if(isset($request->withHistoric)){
            $query->with(['historicPrices' => function($q) {
                $q->latest('created_at')->limit(1);
            }]);
        }

        $query = $query->with(['units', 'status', 'variants']); // por default se obtienen las unidades y el status general

        if(isset($request->_workpoint_status) && $request->_workpoint_status){ // Se obtiene el stock de la tienda se se aplica el filtro

            if($request->_workpoint_status == "all"){
                $query = $query->with(['stocks'  => function($query){ $query->where('active',1);} ]);
            }else{
                $workpoints = $request->_workpoint_status;
                $workpoints[] = 1; // Siempre se agrega el status de la sucursal
                $query = $query->with(['stocks' => function($query) use($workpoints){ //Se obtienen los stocks de todas las sucursales que se pasa el arreglo
                    $query->whereIn('_workpoint', $workpoints)->distinct();
                }]);
            }
        }else{
            if(isset($request->with_stock_cedis) && $request->with_stock_cedis){
                $query = $query->with(['stocks' => function($query) use($request){
                    $query->whereIn('_workpoint',[$request->with_stock_cedis,$request->_workpoint]); //Con stock
                }]);
            }else{
                $query = $query->with(['stocks' => function($query) use($workpoint){ //Se obtiene el stock de la sucursal
                    $query->where('_workpoint', $workpoint)->distinct();
                }]);
            }

        }

        if(isset($request->with_locations) && $request->with_locations){ //Se puede agregar todas las ubicaciones de la sucursal
            $query = $query->with(['locations' => function($query) use ($workpoint) {
                $query->where('deleted_at',null)
                ->whereHas('celler', function($query) use ($workpoint) {
                    $query->where([['_workpoint', $workpoint]]);
                });
            }]);
        }
        if(isset($request->with_locations_loc) && $request->with_locations_loc){ //Se puede agregar todas las ubicaciones de la sucursal
            $data = [
                "workpoint"=>$workpoint,
                "rol"=>$request->with_locations_loc
            ];
            $query = $query->with(['locations' => function($query) use ($data) {
                $query->with('celler')
                ->where('deleted_at',null)
                ->whereHas('celler', function($query) use ($data) {
                    $rol = $data['rol'];
                    $query->where([['_workpoint', $data['workpoint']]]);
                    if(in_array($rol, [1,2,5,6,12,22,18])){//admins
                        $query = $query;
                    }else if(in_array($rol, [24,4,17,15,16,20])){//almacen
                        $query = $query->where('_type',1);
                    }else if(in_array($rol, [8,9,27,28])){//ventas
                        $query = $query->where('_type',2);
                    }
                    ;
                });
            }]);
        }

        if(isset($request->check_stock) && $request->check_stock){ //Se puede agregar el filtro de busqueda para validar si tienen o no stocks los productos
            if($request->with_stock){
                $query = $query->whereHas('stocks', function(Builder $query) use($workpoint){
                    $query->where('_workpoint', $workpoint)->where('stock', '>', 0); //Con stock
                });
            }else{
                $query = $query->whereHas('stocks', function(Builder $query) use($workpoint){
                    $query->where('_workpoint', $workpoint)->where('stock', '<=', 0); //Sin stock
                });
            }
        }

        if(isset($request->with_prices) && $request->with_prices){ //Se puede agregar los precios de lista del producto
            $query = $query->with(['prices' => function($query){
                $query->whereIn('_type', [1, 2, 3, 4])->orderBy('id'); //Solo se envian los precios de Menudeo, Mayoreo, Docena o Media caja y caja
                //Los demas precios no seran mostrados por regla de negocio
            }]);
        }
        if(isset($request->with_prices_Invoice) && $request->with_prices_Invoice){
            $query = $query->with(['prices' => function($q) { $q->where('id',7); } ]);
        }


        if(isset($request->limit) && $request->limit){ //Se puede agregar un limite de los resultados mostrados
            $query = $query->limit($request->limit);
        }

        // $query = $query->with(['variants']);

        if(isset($request->paginate) && $request->paginate){
            $products = $query->orderBy('_status', 'asc')->paginate($request->paginate);
        }else{
            $products = $query->orderBy('_status', 'asc')->get();
        }
        return response()->json($products);
    }

    public function getMassiveProducts(Request $request){
        // Función para obtener los productos y obtener la lista de los que se encontraron y no
        $codes = $request->codes;
        $workpoint = $request->_workpoint;
        $products = [];
        $notFound = [];
        $uniques = array_unique($codes);
        $repeat = array_values(array_diff_assoc($codes, $uniques));
        foreach($uniques as $code){
            $product = ProductVA::with([
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
            ])
            ->whereHas('variants', function(Builder $query) use ($code){
                $query->where('barcode', $code);
            })
            ->orWhere(function($query) use($code){
                $query->where('name', $code);
            })
            ->orWhere(function($query) use($code){
                $query->where('code', $code);
            })
            ->first();
            if($product){
                array_push($products, $product);
            }else{
                array_push($notFound, $code);
            }
        }

        return response()->json([
            "products" => $products,
            "fails" => [
                "notFound" => $notFound,
                "repeat" => $repeat
            ]
        ]);
    }

    public function obtProductSections(Request $request){
        $workpoint = $request->workpoint;
        $sectionId = $request->ubicacion;
        $seccion = $request->seccion;
        // return $sectionId;
        $allIds = [];
        $sections = CellerSectionVA::with(['children' => fn($q) => $q->whereNull('deleted_at')])->whereIn('id',$sectionId)->get();
        if (!$sections) {
            return response()->json([], 404);
        }
        foreach($sections as $section){
        $allIds[] = $section->getAllDescendantIds();
        }
        // return $allIds;

        $products = ProductVA::with([
            'units',
            'variants',
            'status',
            'category.familia.seccion',
            'locations' => function($query) use ($workpoint) {
                $query->with('celler')
                ->whereNull('deleted_at')
                ->whereHas('celler', function($query) use ($workpoint) {
                    $query->where([['_workpoint', $workpoint]]);
                });
            },
        ])
        ->whereHas('category.familia.seccion', function($query) use ($seccion) {
            $query->where('id',$seccion);
        })
        ->whereHas('locations', function($q) use($allIds){ $q->whereIn('id',$allIds);})
        ->where('_status','!=',4)
        ->get();
        return response()->json($products);
    }

    public function obtProductSLocation(Request $request){
        $workpoint = $request->workpoint;
        $seccion = $request->seccion;
        $productos = ProductVA::with([
            'providers',
            'makers',
            'stocks' => function($query) use ($workpoint) {
                $query->where([["gen", ">", 0], ["_workpoint", $workpoint]])
                ->orWhere([["exh", ">", 0], ["_workpoint", $workpoint]]);
            },
            'locations' => function($query) use ($workpoint) {
                $query->where('deleted_at',null)->whereHas('celler', function($query) use ($workpoint) {
                    $query->where('_workpoint', $workpoint);
                });
            },
            'category.familia.seccion',
            'status'
        ])
        ->whereHas('stocks', function($query) use ($workpoint) {
            $query->where([["gen", ">", 0], ["_workpoint", $workpoint]])
            ->orWhere([["exh", ">", 0], ["_workpoint", $workpoint]]);
        })
        ->whereHas('locations', function($query) use ($workpoint) {
            $query->where('deleted_at',null)->whereHas('celler', function($query) use ($workpoint) {
                $query->where('_workpoint', $workpoint);
        });},'<=',0)
        ->whereHas('category.familia.seccion', function($query) use ($seccion) {
            $query->where('id',$seccion);
        })
        ->where('_status', '!=', 4)
        ->get();
        return response()->json($productos);
    }

    public function addCyclecount(Request $request){
        $cyclecounts = [];
        $warehouses = [
            ["id" => 'GEN', "name" => 'General'],
            ["id" => 'EXH', "name" => 'Exhibicion'],
        ];

        // ids de productos enviados
        $_products = $request->collect('products')->pluck('id')->values()->all();

        // usuario que crea (id de account)
        $created_by = $request->_account ?? null;

        // validación mínima
        if (empty($_products) || !$created_by) {
            return response()->json([
                'success' => false,
                'message' => 'Faltan productos o usuario creador'
            ], 422);
        }

        foreach ($warehouses as $warehouse) {
            // cada warehouse se procesa en su propia transacción
            try {
                $result = DB::transaction(function() use ($request, $warehouse, $_products, $created_by) {
                    $counter = CycleCountVA::create([
                        'notes' => $request->notes ?? "",
                        '_workpoint' => $request->_workpoint,
                        '_created_by' => $created_by,
                        '_type' => $request->type['val']['id'] ?? null,
                        '_status' => 1
                    ]);

                    $this->log(1, $counter, $created_by);

                    if ($warehouse['id'] === 'GEN') {
                        $responsables = $request->collect('resgen')->pluck('staff.id_va')->filter()->values()->all();
                    } else {
                        $responsables = $request->collect('resexh')->pluck('staff.id_va')->filter()->values()->all();
                    }

                    if (!empty($responsables)) {
                        $counter->responsables()->syncWithoutDetaching($responsables);
                    }

                    // traer productos con stocks y ubicaciones filtradas por workpoint
                    $products = ProductVA::with([
                        'stocks' => function($q) use ($counter) {
                            $q->where('_workpoint', $counter->_workpoint);
                        },
                        'locations' => function($q) use ($counter) {
                            $q->whereNull('deleted_at')->whereHas('celler', function($q2) use ($counter){
                                $q2->where('_workpoint', $counter->_workpoint);
                            });
                        }
                    ])->whereIn('id', $_products)->get();

                    $products_add = [];

                    foreach ($products as $product) {
                        $stock = 0;
                        if ($product->relationLoaded('stocks') && $product->stocks->count() > 0) {
                            if ($warehouse['id'] === 'GEN') {
                                // $responsables = $request->collect('resgen')->pluck('staff.id_va')->filter()->values()->all();
                                $stock = $product->stocks[0]->pivot->gen ?? 0;
                            } else if ($warehouse['id'] === 'EXH') {
                                // $responsables = $request->collect('resexh')->pluck('staff.id_va')->filter()->values()->all();
                                $stock = $product->stocks[0]->pivot->exh ?? 0;
                            }
                            // $stock = $product->stocks[0]->pivot->stock ?? 0;
                        }

                        $counter->products()->attach($product->id, [
                            'stock' => $stock,
                            'stock_acc' => $stock > 0 ? null : 0,
                            'details' => json_encode(["editor" => ""])
                        ]);

                        $products_add[] = [
                            "id" => $product->id,
                            "code" => $product->code,
                            "name" => $product->name,
                            "description" => $product->description,
                            "dimensions" => $product->dimensions,
                            "pieces" => $product->pieces,
                            "ordered" => [
                                "stocks" => $stock,
                                "stocks_acc" => $stock > 0 ? null : 0,
                                "details" => ["editor" => ""]
                            ],
                            "units" => $product->units,
                            "locations" => $product->locations->map(function($location){
                                return [
                                    "id" => $location->id,
                                    "name" => $location->name,
                                    "alias" => $location->alias,
                                    "path" => $location->path
                                ];
                            })->values()->all()
                        ];
                    }

                    $counter->settings = json_encode(["warehouse" => $warehouse]);
                    $counter->_status = 2;
                    $counter->save();
                    $this->log(2, $counter, $created_by);
                    return [
                        'counter' => $counter->fresh(['workpoint', 'created_by', 'type', 'status', 'responsables', 'log'])->loadCount('products'),
                    ];
                }, 5);

                if ($result && isset($result['counter'])) {
                    $cyclecounts[] = [
                        'warehouse' => $warehouse,
                        'counter' => $result['counter'],
                    ];
                }
            } catch (\Throwable $e) {
                \Log::error('addCyclecount error for warehouse '.$warehouse['id'].': '.$e->getMessage(), [
                    'exception' => $e
                ]);
                return response()->json([
                    'success' => false,
                    'message' => "Error al procesar warehouse {$warehouse['id']}",
                    'error' => $e->getMessage()
                ], 500);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $cyclecounts
        ]);
    }

    public function log($case, CycleCountVA $inventory, $user){
        $account = AccountVA::find($user);
        $responsable = $account ? ($account->names . ' ' . ($account->surname_pat ?? '')) : 'Desconocido';

        switch($case){
            case 1:
                $inventory->log()->attach(1, [
                    'details' => json_encode(["responsable" => $responsable]),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                return true;

            case 2:
                if ($inventory->products()->count() > 0) {
                    $inventory->log()->attach(2, [
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
                        'details' => json_encode(["responsable" => $responsable]),
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    return true;
                }
                return false;

            case 4:
                $inventory->log()->attach(4, [
                    'details' => json_encode(["responsable" => $responsable]),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                return true;

            default:
                return false;
        }
    }

    public function saveFinalStock(CycleCountVA $inventory){
        $workpoint = $inventory->_workpoint;
        // $warehouse = json_decode($inventory->settings)['warehouse']->id;

        $settings = json_decode($inventory->settings);
        $warehouse = $settings->warehouse;   // objeto
        $warehouseId = $warehouse->id;

        foreach($inventory->products as $product){
            $stock_store = $product->stocks->filter(function($stock) use ($workpoint){
                return ($stock->id ?? $stock->_workpoint ?? null) == $workpoint;
            })->values()->all();
            if ($warehouseId === 'GEN') {
                $stock = count($stock_store) > 0 ? ($stock_store[0]->pivot->gen ?? 0) : 0;
            }else if  ($warehouseId === 'EXH') {
                $stock = count($stock_store) > 0 ? ($stock_store[0]->pivot->exh ?? 0) : 0;
            }
            $inventory->products()->updateExistingPivot($product->id, ["stock_end" => $stock]);
        }
    }

    public function getCyclecount(Request $request){
        $id = $request->cyclecount;
        $rol = $request->_rol;
        $uid = $request->id;

        $cyclecount = CycleCountVA::find($id);
        if($cyclecount){
            $cyclecount->load(['workpoint', 'created_by', 'type', 'status', 'responsables', 'log']);
            $data = [
                "rol"=>$rol,
                "workpoint"=>$cyclecount->_workpoint
            ];
            $cyclecount = $cyclecount->load(['products.locations' => function($query)use($data){
                    $query->with('celler')->where('deleted_at',null)->whereHas('celler', function($query) use($data) {
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
        $account = AccountVA::find($request->_user);
        $inventory = CycleCountVA::find($request->_inventory);
        // $settings = $request->settings;
        if($inventory){
            $inventory->products()->updateExistingPivot($request->_product, ['stock_acc' => $request->stock , "details" => json_encode(["editor" => $account])]);
            return response()->json(["success" => true]);
        }
        return response()->json(["success" => false, "message" => "Folio de inventario no encontrado"]);
    }

    public function nextStep(Request $request){ // Función para cambiar el status de un conteo ciclico
        $workpoint = $request->workpoint;
        $user = $request->user;
        $inventory = CycleCountVA::find($request->_inventory);
        if($inventory){
            $status = isset($request->_status) ? $request->_status : $inventory->_status+1;
            if($status>0 && $status<5){
                $result = $this->log($status, $inventory,$user);
                if($result){
                    $inventory->_status= $status;
                    $inventory->save();
                    $inventory->load(['workpoint', 'created_by', 'type', 'status', 'responsables', 'log'])->loadCount('products');
                }
                return response()->json(["success" => $result, 'inventario' => $inventory]);
            }
            return response()->json(["success" => false, "message" => "Status no válido"]);
        }else{
            return response()->json(["success" => false, "message" => "Clave de inventario no válido"]);
        }
    }



}
