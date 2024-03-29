<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PDFController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\ResourcesController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/create-pdf-file', [StaffController::class, 'pdi']);
Route::get('/finalop', [StaffController::class, 'finop']);
Route::get('/actadmin',[ResourcesController::class,'' ]);
Route::get('/assistencias', function () {
    return view('reporte');
});
