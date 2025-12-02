<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\Builder;
use App\Models\ProductVA;
use App\Models\ProvidersVA;
use App\Models\MakersVA;
use App\Models\ProductCategoriesVA;
use App\Models\ProductUnitVA;
use App\Models\Stores;
use App\Models\WorkpointVA;
use App\Models\ControlFigures;
use App\Models\historyPricesVA;


class ReportsController extends Controller
{
    public function Index(){
        return ProductCategoriesVA::whereNotNull('alias')->get();
    }
    public function obtReport(Request $request){
        $startDate = null;
        $endDate = null;
        $time = $request->time;//para ventas,
        $range = $request->range;//para ventas de rango de donde a donde
        $filters = $request->filters;//para saber  donde mero
        $workpoint = $request->workpoint;
        if($time['id'] == 1){
            if(isset($range['from'])){
                $startDate = $range['from'];
                $endDate = $range['to'];
            }else{
                $startDate = $range;
                $endDate = $range;
            }
        }
        $products = ProductVA::with([
            'purchases',
            'prices' => fn($q) => $q->where('_type','!=',7)->orderBy('id'),
            'category.familia.seccion',
            'providers',
            'stocks' => fn($q) => $q->where('id',$workpoint['id_viz']),
            'sales' => function($q) use ($time, $startDate, $endDate, $workpoint,$range) {
                switch($time['id']) {
                    case 1:
                        if ($startDate && $endDate) {
                            $q->whereBetween('created_at', [$startDate, $endDate]);
                        }
                        break;

                    case 2:
                        $q->whereDate('created_at', $range);
                        break;

                    case 3:
                        $q->whereRaw('WEEK(created_at,1) = ?', [$range]);
                        break;

                    case 4:
                        $q->whereMonth('created_at', $range)
                        ->whereYear('created_at', date('Y'));
                        break;
                }
                $q->whereHas('cashRegister', fn($q2) =>
                    $q2->where('_workpoint', $workpoint['id_viz'])
                );
            }
        ])
        ->when(isset($filters['categoria']), fn($q) =>
            $q->whereHas('category', fn($q2) =>
                $q2->where('id', $filters['categoria'])
            )
        )
        ->when(isset($filters['familia']), fn($q) =>
            $q->whereHas('category.familia', fn($q2) =>
                $q2->where('id', $filters['familia'])
            )
        )
        ->when(isset($filters['seccion']), fn($q) =>
            $q->whereHas('category.familia.seccion', fn($q2) =>
                $q2->where('id', $filters['seccion'])
            )
        )
        ->whereHas('stocks', fn($q) =>
            $q->where('id', $workpoint['id_viz'])
        )
        ->where('_status', '!=', 4)
        ->get();

        return response()->json($products,200);
    }

    public function reportWarehouses(Request $request){
        $filters = $request->all();
        $products = ProductVA::with([
                'providers',
                'makers',
                'category.familia.seccion',
                'stocks'=> fn($q) => $q->where('active', 1)
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
            ->withSum(['stocksSum as SumStock'],'stock')
            ->withSum(['salesYearSum as SalesYear'],'amount')
            ->withSum(['salesSubYearSum as SalesSubYear'],'amount')
            ->withSum(['purchasesSum as PurchaseYear'],'amount')
            ->where('_status','!=',4)->get();
            return response()->json($products);
    }

    public function indexvpi(){
        $seccion = ProductCategoriesVA::where([['deep',0],['alias','!=',null]])->get();
        $familia = ProductCategoriesVA::where([['deep',1],['alias','!=',null]])->get();
        $categoria = ProductCategoriesVA::where([['deep',2],['alias','!=',null]])->get();

        $res = [
            "seccion"=>$seccion,
            "familia"=>$familia,
            "categoria"=>$categoria
        ];
        return response()->json($res);
    }

    public function getReport(Request $request){
        // return response()->json(['ok' => 'entra']);
        try {
            $workpoint = $request->workpoint;
            $secciones = $request->secciones;
            $catalogo = $this->catalogo($workpoint,$secciones);
            // return response()->json(['ok' => 'catalogo']);
            $conStock = $this->conStock($workpoint,$secciones);
            // return response()->json(['ok' => 'conStock']);
            $conStockUbicados = $this->conStockUbicados($workpoint,$secciones);
            // return response()->json(['ok' => 'conStockUbicados']);
            $conStockSinUbicar = $this->conStockSinUbicar($workpoint,$secciones);
            // return response()->json(['ok' => 'conStockSinUbicar']);
            $sinStock = $this->sinStock($workpoint,$secciones);
            // return response()->json(['ok' => 'sinStock']);
            $sinStockUbicados = $this->sinStockUbicados($workpoint,$secciones);
            // return response()->json(['ok' => 'sinStockUbicados']);
            $sinMaximos = $this->sinMaximos($workpoint,$secciones);
            // return response()->json(['ok' => 'sinMaximos']);
            $generalVsExhibicion = $this->generalVsExhibicion($workpoint,$secciones);
            // return response()->json(['ok' => 'generalVsExhibicion']);
            $generalVsCedis = $this->generalVsCedis($workpoint,$secciones);
            // return response()->json(['ok' => 'generalVsCedis']);
            $conMaximos = $this->conMaximos($workpoint,$secciones);
            // return response()->json(['ok' => 'conMaximos']);
            $negativos = $this->negativos($workpoint,$secciones);
            // return response()->json(['ok' => 'negativos']);
            $cedisStock = $this->cedisStock($workpoint,$secciones);
            // return response()->json(['ok' => 'cedisStock']);

            $res = [
                "catalogo"=>$catalogo,//ok
                "conStock"=>$conStock,//ok
                "conStockUbicados"=>$conStockUbicados,//ok
                "conStockSinUbicar"=>$conStockSinUbicar,
                "sinStock"=>$sinStock,
                "sinStockUbicados"=>$sinStockUbicados,
                "sinMaximos"=>$sinMaximos,
                "generalVsExhibicion"=>$generalVsExhibicion,
                "generalVsCedis"=>$generalVsCedis,
                "conMaximos"=>$conMaximos,
                "negativos"=>$negativos,
                "cedisStock"=>$cedisStock,
            ];
        return response()->json($res);


        } catch (\Exception $e) {
            dd($e);
        }
    }

    public function catalogo($workpoint,$seccion){
        $productos = ProductVA::with([
        'providers',
        'makers',
        'stocks' => function($query) use ($workpoint){
            $query->where("_workpoint", $workpoint);
        },
        'locations' => function($query)use ($workpoint){
            $query->whereHas('celler', function($query) use ($workpoint){
                $query->where('_workpoint', $workpoint);
            });
        } ,
        'category.familia.seccion',
         'status'
         ])->where('_status', '!=', 4)
         ->whereHas('category.familia.seccion', function($query) use ($seccion) {
            $query->whereIn('id',$seccion);
            })
         ->get()->toArray();;
        return $productos;
    }


    public function conStock($workpoint,$seccion){
        $productos = ProductVA::with([
        'makers',
        'providers',
        'stocks' => function($query) use($workpoint){
                $query->where([["gen", ">", 0], ["_workpoint", $workpoint]])
                ->orWhere([["exh", ">", 0], ["_workpoint", $workpoint]]);
        },
        'locations' => function($query) use($workpoint){
            $query->whereHas('celler', function($query) use($workpoint){
                $query->where('_workpoint', $workpoint);
            });
        },
        'category.familia.seccion',
        'status'
        ])
        ->whereHas('stocks', function($query) use($workpoint){
            $query->where([["gen", ">", 0], ["_workpoint", $workpoint]])
            ->orWhere([["exh", ">", 0], ["_workpoint", $workpoint]]);
        })
        ->whereHas('category.familia.seccion', function($query) use ($seccion) {
            $query->whereIn('id',$seccion);
            })
        ->where('_status', '!=', 4)->get()->toArray();;
        return $productos;
    }

    public function conStockUbicados($workpoint,$seccion){
        $productos = ProductVA::with([
        'providers',
        'makers',
        'stocks' => function($query) use ($workpoint){
            $query->where([["gen", ">", "0"], ["_workpoint", $workpoint]])
            ->orWhere([["exh", ">", 0], ["_workpoint", $workpoint]]);
        },
        'locations' => function($query) use ($workpoint){
            $query->where('deleted_at',null)->whereHas('celler', function($query) use ($workpoint){
                $query->where('_workpoint', $workpoint);
            });
        },
        'category.familia.seccion',
        'status'
        ])
        ->whereHas('stocks', function($query) use ($workpoint){
            $query->where([["gen", ">", 0], ["_workpoint", $workpoint]])
            ->orWhere([["exh", ">", 0], ["_workpoint", $workpoint]]);})
        ->whereHas('locations', function($query) use ($workpoint){
            $query->where('deleted_at',null)->whereHas('celler', function($query) use ($workpoint){
                $query->where('_workpoint', $workpoint);
            });},'>',0)
        ->whereHas('category.familia.seccion', function($query) use ($seccion) {
            $query->whereIn('id',$seccion);
            })
        ->where('_status', '!=', 4)->get()->toArray();;

        return $productos;
    }

    public function conStockSinUbicar($workpoint,$seccion){
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
            $query->whereIn('id',$seccion);
            })
        ->where('_status', '!=', 4)->get()->toArray();;
        return $productos;
    }

    public function sinStock($workpoint,$seccion){
        $productos = ProductVA::with([
            'providers',
            'makers',
            'stocks' => function($query) use ($workpoint) {
                $query->where([["gen", "<=", 0],["exh", "<=", 0], ["_workpoint", $workpoint]]);
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
                $query->where([["gen", "<=", 0],["exh", "<=", 0], ["_workpoint", $workpoint]]);})
            ->whereHas('category.familia.seccion', function($query) use ($seccion) {
                $query->whereIn('id',$seccion);
                })
            ->where('_status', '!=', 4)->get()->toArray();;


        return $productos;
    }

    public function sinStockUbicados($workpoint,$seccion){
        $productos = ProductVA::with([
        'providers',
        'makers',
        'stocks' => function($query) use($workpoint) {
            $query->where([["stock", "<=", "0"], ["_workpoint", $workpoint]]);
        },
        'locations' => function($query)use($workpoint){
            $query->where('deleted_at',null)->whereHas('celler', function($query) use($workpoint) {
                $query->where('_workpoint', $workpoint);
            });
        },
        'category.familia.seccion','status'])
        ->whereHas('stocks', function($query) use($workpoint) {
            $query->where([["stock", "<=", 0], ["stock", "<=", 0], ["_workpoint", $workpoint]]);
        })
        ->whereHas('locations', function($query) use($workpoint) {
            $query->where('deleted_at',null)->whereHas('celler', function($query) use($workpoint) {
                $query->where('_workpoint', $workpoint);});},'>',0)
        ->whereHas('category.familia.seccion', function($query) use ($seccion) {
            $query->whereIn('id',$seccion);
            })
        ->where('_status', '!=', 4)
        ->get()->toArray();;
        return $productos;
    }

    public function sinMaximos($workpoint,$seccion){ // Función que retorna todos los productos que no tiene máximo y si stock
        $productos = ProductVA::with([
        'providers',
        'makers',
        "stocks" => function($query) use($workpoint) {
            $query->where([["stock", ">", 0], ["min", "<=", 0], ["max", "<=", 0], ["_workpoint", $workpoint]]);
        },
        'category.familia.seccion',
        'locations' => function($query)use($workpoint){
            $query->where('deleted_at',null)->whereHas('celler', function($query) use($workpoint) {
                $query->where('_workpoint', $workpoint);
            });
        },
        'status'])
        ->whereHas('stocks', function($query) use($workpoint) {
            $query->where([["stock", ">", 0], ["min", "<=", 0], ["max", "<=", 0], ["_workpoint", $workpoint]]);
        })
        ->whereHas('category.familia.seccion', function($query) use ($seccion) {
            $query->whereIn('id',$seccion);
        })
        ->where('_status', '!=', 4)
        ->get()->toArray();;
        return $productos;
    }

    public function generalVsExhibicion($workpoint,$seccion){
        $productos = ProductVA::with([
        'providers',
        'makers',
        'stocks' => function($query) use ($workpoint) {
            $query->where([["gen", ">", "0"], ["exh", "<=", 0], ["_workpoint", $workpoint]]);
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
            $query->where([["gen", ">", "0"], ["exh", "<=", 0], ["_workpoint", $workpoint]]);
        })
        ->whereHas('category.familia.seccion', function($query) use ($seccion) {
            $query->whereIn('id',$seccion);
        })
        ->where('_status', '!=', 4)
        ->get()->toArray();;

        return $productos;
    }

    public function generalVsCedis($workpoint,$seccion){
        $products = ProductVA::with([
            'locations' => function($query) use ($workpoint) {
                $query->where('deleted_at',null)->whereHas('celler', function($query) use ($workpoint) {
                    $query->where('_workpoint', $workpoint);
                });
            },
            'providers',
            'makers',
            'category.familia.seccion',
            'status',
            'stocks' => function($query) use ($workpoint) { //Se obtiene el stock de la sucursal
                $query->whereIn('_workpoint',[1,2,$workpoint])->distinct();
            }
            ])
            ->whereHas('category.familia.seccion', function($query) use ($seccion) { // Aplicamos el filtro en la relación seccion
                $query->whereIn('id',$seccion);
            })
            ->whereHas('stocks', function($query) { // Solo productos con stock mayor a 0 en el workpoint
                $query->whereIn('_workpoint', [1, 2])
                      ->where('stock', '>', 0); // Filtra solo aquellos con stock positivo
            })
            ->whereHas('stocks', function($query) use ($workpoint) { // Solo productos con stock mayor a 0 en el workpoint
                $query->where('_workpoint',$workpoint)
                      ->where('stock', '=', 0); // Filtra solo aquellos con stock positivo
            })
            ->where('_status','!=',4)->get();
        return $products->toArray();;
    }

    public function conMaximos($workpoint,$seccion){
        $productos = ProductVA::with([
        'providers',
        'makers',
        "stocks" => function($query) use ($workpoint) {
            $query->where([["stock", ">", 0], ["min", ">", 0], ["max", ">", 0], ["_workpoint", $workpoint]]);
        },
        'locations' => function($query)use($workpoint){
            $query->where('deleted_at',null)->whereHas('celler', function($query) use($workpoint) {
                $query->where('_workpoint', $workpoint);
            });
        },
        'category.familia.seccion',
        'status'
        ])->whereHas('stocks', function($query) use ($workpoint) {
            $query->where([["stock", ">", 0], ["min", ">", 0], ["max", ">", 0], ["_workpoint", $workpoint]]);
        })
        ->whereHas('category.familia.seccion', function($query) use ($seccion) { // Aplicamos el filtro en la relación seccion
            $query->whereIn('id',$seccion);
        })
        ->where('_status', '!=', 4)
        ->get()->toArray();;
        return $productos;
    }

    public function negativos($workpoint,$seccion){

        $productos = ProductVA::with([
        'makers',
        'providers',
        'stocks' => function($query) use ($workpoint) {
            $query->where([["_workpoint", $workpoint], ['gen', '<', 0]])->orWhere([["_workpoint", $workpoint], ['exh', '<', 0]]);
        },
        'locations' => function($query) use ($workpoint) {
            $query->where('deleted_at',null)->whereHas('celler', function($query) use ($workpoint) {
                $query->where('_workpoint', $workpoint);
            });
        },
        'category.familia.seccion',
        'status'
        ])->whereHas('stocks', function($query) use ($workpoint) {
            $query->where([["_workpoint", $workpoint], ['gen', '<', 0]])
            ->orWhere([["_workpoint", $workpoint], ['exh', '<', 0]]);
        })
        ->whereHas('category.familia.seccion', function($query) use ($seccion) { // Aplicamos el filtro en la relación seccion
            $query->whereIn('id',$seccion);
        })
        ->where('_status', '!=', 4)
        ->get()->toArray();;
        return $productos;
    }

    public function cedisStock($workpoint,$seccion){
        $products = ProductVA::with([
            'category.familia.seccion',
            'makers',
            'providers',
            'status',
            'locations' => function($query)use($workpoint){
                $query->where('deleted_at',null)->whereHas('celler', function($query) use($workpoint) {
                    $query->where('_workpoint', $workpoint);
                });
            },
            'stocks' => function($query) use ($workpoint) { //Se obtiene el stock de la sucursal
                $query->whereIn('_workpoint',[1,2])->distinct();}
            ])
            ->whereHas('category.familia.seccion', function($query) use ($seccion) { // Aplicamos el filtro en la relación seccion
                $query->whereIn('id',$seccion);
            })
            ->whereHas('stocks', function($query) { // Solo productos con stock mayor a 0 en el workpoint
                $query->whereIn('_workpoint', [1, 2])
                      ->where('stock', '>', 0); // Filtra solo aquellos con stock positivo
            })
            ->where('_status','!=',4)->get()->toArray();;
        return $products;
    }

    // public function getProductsDown(Request $request){
    //     $filters = $request->filters;
    //     $store = $request->store;


    //     $products = ProductVA::with([
    //             'providers',
    //             'makers',
    //             'category.familia.seccion',
    //             'stocks' => fn($q) => $q->where('id',[$store,1,2,16]),
    //         ])
    //         ->withMax(['sales as saleLast' => function ($q) use ($store) {
    //             $q->whereHas('cashRegister', fn($q2) =>
    //                 $q2->where('_workpoint', $store)
    //                 );
    //             }], 'created_at')
    //         ->when(count($filters['categories']) > 0, fn($q) =>
    //             $q->whereHas('category', fn($q2) =>
    //             $q2->whereIn('id', $filters['categories'])))
    //         ->when(count($filters['familys'])> 0, fn($q) =>
    //             $q->whereHas('category.familia', fn($q2) =>
    //             $q2->whereIn('id', $filters['familys'])))
    //         ->when(count($filters['sections'])> 0, fn($q) =>
    //             $q->whereHas('category.familia.seccion', fn($q2) =>
    //             $q2->whereIn('id', $filters['sections'])))
    //         ->where('_status','!=',4)->get();
    //         return response()->json($products);
    // }


    public function getProductsDown(Request $request){
        $filters = $request->filters;
        $store = $request->store;

        $workpoint = WorkpointVA::with('productSeason')->find($store);

        $products = ProductVA::with([
            'providers',
            'makers',
            'category.familia.seccion',
            'stocks' => fn($q) => $q->whereIn('id', [$store, 1, 2, 16]),
        ])
        ->withMax(['sales as saleLast' => function ($q) use ($store) {
            $q->whereHas('cashRegister', fn($q2) =>
                $q2->where('_workpoint', $store)
            );
        }], 'created_at')
        ->when(count($filters['categories']) > 0, fn($q) =>
            $q->whereHas('category', fn($q2) =>
            $q2->whereIn('id', $filters['categories'])))
        ->when(count($filters['familys'])> 0, fn($q) =>
            $q->whereHas('category.familia', fn($q2) =>
            $q2->whereIn('id', $filters['familys'])))
        ->when(count($filters['sections'])> 0, fn($q) =>
            $q->whereHas('category.familia.seccion', fn($q2) =>
            $q2->whereIn('id', $filters['sections'])))
        ->when($workpoint && $workpoint->productSeason && $workpoint->productSeason->isNotEmpty(),
            fn($q) => $q->whereIn('id', $workpoint->productSeason->pluck('id'))
        )
        ->where('_status', '!=', 4)->get();
        return response()->json($products);
    }



}
