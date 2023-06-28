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
Route::post('/checklistiop',[StaffController::class,'checklistiop']);
Route::post('/webhook',[StaffController::class,'webhook']);
Route::post('/syncjustification',[StaffController::class,'justification']);
Route::post('/updatestaff', [StaffController::class, 'updatestaff']);
Route::post('/updatestaffiop', [StaffController::class, 'updatestaffiop']);
Route::get('/checklistfinop', [StaffController::class, 'checklistfinop']);

// mx100-cedis-mkrqpwcczk.dynamic-m.com:1025/assist/public/

//rutas zkteco
Route::get('/pings',[ZktecoController::class,'pings']);
Route::post('/add',[ZktecoController::class,'add']);
Route::get('/maxuaid',[ZktecoController::class,'maxuaid']);
Route::get('/report',[ZktecoController::class,'report']);

Route::get('/suc',[GoogleController::class,'sucursales']);

