<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\ZktecoController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
//rutas monday
Route::post('/syncstaff',[StaffController::class,'replystaff']);
Route::post('/syncjustification',[StaffController::class,'justification']);
//rutas zkteco
Route::get('/pings',[ZktecoController::class,'pings']);
Route::post('/add',[ZktecoController::class,'add']);

