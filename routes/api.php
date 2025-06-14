<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\ZktecoController;
use App\Http\Controllers\MondayController;
use App\Http\Controllers\WappController;
use App\Http\Controllers\ResourcesController;
use App\Http\Controllers\AssistController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\CashierController;
use App\Http\Controllers\RestockController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\DepositsController;
use App\Http\Controllers\OutputsController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\RequisitionController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\UserController;





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
Route::post('/updatestaff', [StaffController::class, 'dropiopupd']);
Route::post('/updatestaffiop', [StaffController::class, 'updatestaffiop']);
Route::get('/checklistfinop', [StaffController::class, 'checklistfinop']);

// mx100-cedis-mkrqpwcczk.dynamic-m.com:1025/assist/public/

//rutas zkteco
Route::get('/pings',[ZktecoController::class,'pings']);
Route::post('/add',[ZktecoController::class,'add']);
Route::get('/maxuaid',[ZktecoController::class,'maxuaid']);
Route::get('/report',[ZktecoController::class,'report']);
Route::post('/insturn', [ZktecoController::class, 'insturn']);
Route::get('/getResourses',[ZktecoController::class, 'getReport']);

Route::get('/suc',[GoogleController::class,'sucursales']);

Route::prefix('/Monday')->group(function(){
    Route::get('/Cifras',[MondayController::class, 'Cifras']);
    Route::post('/cheklistiop',[MondayController::class, 'cheklistiop']);
    Route::post('/cheklistfinop',[MondayController::class, 'cheklistfinop']);
    Route::post('/completestaff',[MondayController::class, 'completestaff']);
    Route::post('/staff',[MondayController::class, 'staff']);
    Route::get('/justification',[MondayController::class, 'justification']);
    Route::get('/getids',[MondayController::class, 'findid']);
    Route::get('/cheklistiopmas',[MondayController::class, 'cheklistiopmas']);
    Route::get('/cheklistfinopmas',[MondayController::class, 'cheklistfinopmas']);

});

Route::prefix('/waap')->group(function(){
    Route::post('/restock',[WappController::class, 'restock']);
});

Route::prefix('/zkt')->group(function(){
    Route::get('/Reportcomplete',[ZktecoController::class, 'completeReport']);
    Route::get('/Index',[ZktecoController::class, 'Index']);
    Route::get('/Ping/{d}',[ZktecoController::class, 'Ping']);
    Route::get('/getDate/{d}',[ZktecoController::class, 'getDate']);
    Route::get('/getRegister/{d}',[ZktecoController::class, 'getRegistros']);
    Route::get('/getRegisDevice/{d}',[ZktecoController::class, 'getRegisDevice']);
    Route::get('/changeDate/{d}',[ZktecoController::class, 'changeDate']);
    Route::delete('/deleteAttendance/{d}',[ZktecoController::class, 'deleteAttendance']);
    Route::post('/Edit',[ZktecoController::class, 'edit']);
    Route::delete('/delete',[ZktecoController::class, 'delete']);

});


Route::prefix('/staff')->group(function(){
    Route::get('',[StaffController::class, 'Index']);
    Route::get('staffReply',[StaffController::class, 'staffReply']);
});
Route::prefix('/users')->group(function(){
    Route::get('getResources/{uid}',[UserController::class, 'getResources']);
    Route::post('createUser',[UserController::class, 'createUser']);
    Route::post('createMasiveUser',[UserController::class, 'createMasiveUser']);
    Route::post('trySignin',[UserController::class, 'trySignin']);

});


Route::prefix('/resources')->group(function(){
    Route::post('/actadmin',[ResourcesController::class, 'actasAdministrativas']);
});

Route::prefix('/assist')->group(function(){
    Route::get('/report',[AssistController::class, 'report']);
});

Route::prefix('/Products')->group(function(){
    Route::post('/translate',[ProductsController::class, 'translateWarehouses']);
    Route::post('/transfers',[ProductsController::class, 'transferStores']);
    Route::post('/trapasDev',[ProductsController::class, 'trapasDev']);
    Route::post('/trapasAbo',[ProductsController::class, 'trapasAbo']);
    Route::post('/updev',[ProductsController::class, 'ignoredAbo']);
    Route::post('/reportDepure',[ProductsController::class, 'reportDepure']);
    Route::post('/replacecode',[ProductsController::class, 'replacecode']);
    Route::post('/invoiceReceived',[ProductsController::class, 'invoiceReceived']);

});

Route::prefix('/admincli')->group(function(){
    Route::get('/',[ResourcesController::class, 'Index']);
    Route::post('/',[ResourcesController::class, 'Create']);
    Route::get('/solicitudes',[ResourcesController::class, 'getSolicitud']);
    Route::get('/sol',[ResourcesController::class, 'getsol']);
    Route::get('/getclient',[ResourcesController::class, 'getclient']);
    Route::get('/syncClient',[ResourcesController::class, 'syncClient']);
    Route::post('/addClient',[ResourcesController::class, 'createClient']);
    Route::patch('/ignoredClient',[ResourcesController::class, 'IgnoredClient']);
    Route::patch('/Restore',[ResourcesController::class, 'Restore']);
    Route::patch('/Delete',[ResourcesController::class, 'Delete']);

    // Route::get('/getStaff',[ResourcesController::class, 'getStaff']);
});

Route::prefix('/salidas')->group(function(){
    Route::get('/',[ResourcesController::class, 'getSldas']);
    // Route::get('/getStaff',[ResourcesController::class, 'getStaff']);
});

Route::prefix('/abonos')->group(function(){
    Route::get('/getSuc',[ResourcesController::class, 'getSuc']);
    Route::post('/getDev',[ResourcesController::class, 'getDev']);
    Route::post('/gettras',[ResourcesController::class, 'gettras']);
    Route::post('/iniproces',[ResourcesController::class, 'iniproces']);
    Route::post('/nabo',[ResourcesController::class, 'nabo']);
    Route::post('/ninv',[ResourcesController::class, 'ninv']);
    Route::post('/nent',[ResourcesController::class, 'nent']);


});

Route::prefix('/cashier')->group(function(){
    Route::get('/getStaff/{id}',[CashierController::class, 'getStaff']);
    Route::get('/getPrinters/{id}',[CashierController::class, 'getPrinter']);
    Route::get('/getCutsBoxes/{sid}',[CashierController::class, 'getCutsBoxes']);
    Route::post('/AddFile',[CashierController::class, 'AddFile']);
    Route::post('/Opening',[CashierController::class, 'Opening']);
    Route::post('/getCurrenCut',[CashierController::class, 'getCurrenCut']);
});

Route::prefix('/restock')->group(function(){
    Route::get('/getSupply/{sid}',[RestockController::class, 'getSupply']);
    Route::get('/getVerified/{sid}',[RestockController::class, 'getVerified']);
    Route::get('/getChof/{sid}',[RestockController::class, 'getChof']);
    Route::get('/getInvoices',[RestockController::class, 'getInvoices']);
    Route::get('/AutomateRequisition',[RestockController::class, 'AutomateRequisition']);
    Route::get('/getStores',[RestockController::class, 'getStores']);
    Route::get('/getCheck/{cli}',[RestockController::class, 'getCheck']);
    Route::post('/saveSupply',[RestockController::class, 'saveSupply']);
    Route::post('/saveVerified',[RestockController::class, 'saveVerified']);
    Route::post('/saveChofi',[RestockController::class, 'saveChofi']);
    Route::post('/saveCheck',[RestockController::class, 'saveCheck']);
    Route::post('/getSalida',[RestockController::class, 'getSalida']);
    Route::post('/getTransfer',[RestockController::class, 'getTransfer']);
    Route::post('/getSupplier',[RestockController::class, 'getSupplier']);
    Route::post('/changeStatus',[RestockController::class, 'changeStatus']);
    Route::post('/sendMessage',[RestockController::class, 'sendMessages']);
    Route::post('/getData',[RestockController::class, 'getData']);
});

Route::prefix('/sales')->group(function(){
    Route::get('/getSale',[SalesController::class, 'Index']);
    Route::get('/getStores',[SalesController::class, 'getStores']);
});

Route::prefix('/requisition')->group(function(){
    Route::get('/getRequisitionsStore',[RequisitionController::class, 'getRequisitionsStore']);
    Route::get('/{id}',[RequisitionController::class, 'getRequisitions']);
    Route::get('/{id}/{req}',[RequisitionController::class, 'getRequisition']);
    Route::get('/{id}/{req}/print',[RequisitionController::class, 'printReq']);
    Route::get('/{id}/{req}/change',[RequisitionController::class, 'changeStatus']);
    Route::post('/createRequisition',[RequisitionController::class, 'createRequisition']);
    Route::post('/finishRequisition',[RequisitionController::class, 'finishRequisition']);

});

Route::prefix('/transfer')->group(function(){
    Route::get('/getTransfers/{sid}',[TransferController::class, 'Index']);
    Route::get('/getTransfer/{oid}',[TransferController::class, 'getTransfer']);
    Route::post('/getTransfersDate',[TransferController::class, 'getTransfersDate']);
    Route::post('/addTransfer',[TransferController::class, 'addTransfer']);
    Route::post('/addProduct',[TransferController::class, 'addProduct']);
    Route::post('/addProductMasive',[TransferController::class, 'addProductMasive']);
    Route::post('/editProduct',[TransferController::class, 'editProduct']);
    Route::post('/removeProduct',[TransferController::class, 'removeProduct']);
    Route::post('/endTransfer',[TransferController::class, 'endTransfer']);
    Route::post('/transferPreventa',[TransferController::class, 'transferPreventa']);

});

Route::prefix('/output')->group(function(){
    Route::get('/getOutputs/{sid}',[OutputsController::class, 'Index']);
    Route::get('/getOutput/{oid}',[OutputsController::class, 'getOutput']);
    Route::post('/getOutsDate',[OutputsController::class, 'getOutsDate']);
    Route::post('/addOutputs',[OutputsController::class, 'addOuts']);
    Route::post('/addProductMasive',[OutputsController::class, 'addProductMasive']);
    Route::post('/addProduct',[OutputsController::class, 'addProduct']);
    Route::post('/editProduct',[OutputsController::class, 'editProduct']);
    Route::post('/removeProduct',[OutputsController::class, 'removeProduct']);
    Route::post('/endOutput',[OutputsController::class, 'endOutput']);
    Route::post('/outputPreventa',[OutputsController::class, 'outputPreventa']);
});


Route::prefix('/deposits')->group(function(){
    Route::post('/getForms',[DepositsController::class,'getForms']);
    Route::post('/getFormsStore/{sid}',[DepositsController::class,'getFormsStore']);
    Route::post('/forms',[DepositsController::class, 'newForm']);
    Route::post('/changeStatus',[DepositsController::class, 'changeStatus']);
    Route::post('/changeTicket',[DepositsController::class, 'changeTicket']);


});

Route::prefix('/refunds')->group(function(){
    Route::get('/getRefunds/{sid}',[RefundController::class,'Index']);
    Route::get('/getRefundDirerences/{sid}',[RefundController::class,'getRefundDirerences']);
    Route::get('/getRefund/{sid}/{rid}',[RefundController::class,'getRefund']);
    Route::get('/getRefundto/{sid}/{rid}',[RefundController::class,'getRefundTo']);
    Route::post('/addRefund',[RefundController::class,'addRefund']);
    Route::post('/addProduct',[RefundController::class,'addProduct']);
    Route::post('/editProduct',[RefundController::class,'editProduct']);
    Route::post('/editProductReceipt',[RefundController::class,'editProductReceipt']);
    Route::post('/deleteProduct',[RefundController::class,'deleteProduct']);
    Route::post('/endRefund',[RefundController::class,'endRefund']);
    Route::post('/nexState',[RefundController::class,'nexState']);
    Route::post('/finallyRefund',[RefundController::class,'finallyRefund']);
    Route::post('/correction',[RefundController::class,'correction']);

});

Route::prefix('/cashs')->group(function(){
    Route::post('/getWithdrawals',[CashController::class,'getWithdrawals']);
    Route::post('/reprintWithdrawal',[CashController::class,'reprintWithdrawal']);
    Route::post('/reprintSale',[CashController::class,'reprintSale']);
    Route::post('/getDependiente',[CashController::class,'getDependiente']);
    Route::post('/index',[CashController::class,'index']);
    Route::post('/getCash',[CashController::class,'getCash']);
    Route::post('/openCash',[CashController::class,'openCash']);
    Route::post('/addSale',[CashController::class,'addSale']);
    Route::post('/addSaleStandar',[CashController::class,'addSaleStandar']);
    Route::post('/closeCash',[CashController::class,'closeCash']);
    Route::post('/addWitrawal',[CashController::class,'addWitrawal']);
    Route::post('/addIngress',[CashController::class,'addIngress']);
    Route::post('/getIngress',[CashController::class,'getIngress']);
    Route::post('/reprintIngress',[CashController::class,'reprintIngress']);
    Route::post('/addAdvances',[CashController::class,'addAdvances']);
    Route::post('/getSales',[CashController::class,'getSales']);
    Route::post('/RepliedSales',[CashController::class,'RepliedSales']);
    // Route::post('/getIngress',[CashController::class,'getIngress']);
    // Route::post('/reprintIngress',[CashController::class,'reprintIngress']);
});

