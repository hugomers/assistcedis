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
use App\Http\Controllers\InvoicesController;
use App\Http\Controllers\InvoicesReceived;
use App\Http\Controllers\PrinterController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\locationController;
use App\Http\Controllers\WithdrawalsController;



Route::prefix('/users')->group(function(){
    Route::post('trySignin',[UserController::class, 'trySignin']);
});


Route::middleware('auth')->group(function(){

    Route::post('/syncstaff',[StaffController::class,'replystaff']);
    Route::post('/checklistiop',[StaffController::class,'checklistiop']);
    Route::post('/webhook',[StaffController::class,'webhook']);
    Route::post('/syncjustification',[StaffController::class,'justification']);
    Route::post('/updatestaff', [StaffController::class, 'dropiopupd']);
    Route::post('/updatestaffiop', [StaffController::class, 'updatestaffiop']);
    Route::get('/checklistfinop', [StaffController::class, 'checklistfinop']);
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

    Route::prefix('/reports')->group(function(){
        Route::get('',[ReportsController::class, 'Index']);
        Route::post('reportWarehouses',[ReportsController::class, 'reportWarehouses']);
        Route::post('obtReport',[ReportsController::class, 'obtReport']);
    });


    Route::prefix('/users')->group(function(){
        Route::get('getResources/{uid}',[UserController::class, 'getResources']);
        Route::post('createUser',[UserController::class, 'createUser']);
        Route::post('createMasiveUser',[UserController::class, 'createMasiveUser']);
        // Route::post('trySignin',[UserController::class, 'trySignin']);
        Route::post('changeAvatar',[UserController::class, 'changeAvatar']);
    });


    Route::prefix('/resources')->group(function(){
        Route::post('/actadmin',[ResourcesController::class, 'actasAdministrativas']);
    });

    Route::prefix('/assist')->group(function(){
        Route::get('/report',[AssistController::class, 'report']);
    });

    Route::prefix('/Products')->group(function(){
        Route::get('/index',[ProductsController::class, 'index']);
        Route::get('/getProduct/{id}',[ProductsController::class, 'getProduct']);
        Route::get('/searchCode/{id}',[ProductsController::class, 'searchCode']);
        Route::get('/searchBarcode/{id}',[ProductsController::class, 'searchBarcode']);
        Route::get('/getWorkpoinProduct/{sid}',[ProductsController::class, 'getWorkpoinProduct']);


        // Route::get('/getProduct/{id}',[ProductsController::class, 'getProduct']);

        Route::post('/translate',[ProductsController::class, 'translateWarehouses']);
        Route::post('/transfers',[ProductsController::class, 'transferStores']);
        Route::post('/trapasDev',[ProductsController::class, 'trapasDev']);
        Route::post('/trapasAbo',[ProductsController::class, 'trapasAbo']);
        Route::post('/updev',[ProductsController::class, 'ignoredAbo']);
        Route::post('/reportDepure',[ProductsController::class, 'reportDepure']);
        Route::post('/replacecode',[ProductsController::class, 'replacecode']);
        Route::post('/invoiceReceived',[ProductsController::class, 'invoiceReceived']);
        Route::post('/autoComplete',[ProductsController::class,'autoComplete']);
        Route::post('/search-exact',[ProductsController::class,'searchExact']);
        Route::post('/genBarcode',[ProductsController::class, 'genBarcode']);
        Route::post('/checkCodesBatch',[ProductsController::class, 'checkCodesBatch']);
        Route::post('/highProducts',[ProductsController::class, 'highProducts']);
        Route::post('/highPrices',[ProductsController::class, 'highPrices']);
        Route::post('/lookupProducts',[ProductsController::class, 'lookupProducts']);
        Route::post('/checkLabels',[ProductsController::class, 'checkLabels']);
        Route::post('/setMin',[ProductsController::class, 'setMin']);
        Route::post('/setMax',[ProductsController::class, 'setMax']);
        Route::post('/setMassisveMinMax',[ProductsController::class, 'setMassisveMinMax']);

    });

    Route::prefix('/admincli')->group(function(){
        Route::get('/',[ResourcesController::class, 'Index']);
        Route::post('/',[ResourcesController::class, 'Create']);
        Route::get('/solicitudes',[ResourcesController::class, 'getSolicitud']);
        Route::get('/sol',[ResourcesController::class, 'getsol']);
        Route::get('/getclient',[ResourcesController::class, 'getclient']);
        Route::get('/syncClient',[ResourcesController::class, 'syncClient']);
        Route::post('/addClient',[ResourcesController::class, 'createClient']);
        Route::post('/updateImageClient',[ResourcesController::class, 'updateImageClient']);
        Route::patch('/ignoredClient',[ResourcesController::class, 'IgnoredClient']);
        Route::patch('/Restore',[ResourcesController::class, 'Restore']);
        Route::patch('/Delete',[ResourcesController::class, 'Delete']);

        // Route::get('/getStaff',[ResourcesController::class, 'getStaff']);
    });

    Route::prefix('/Client')->group(function(){
        Route::get('/',[ClientController::class, 'Index']);
        Route::post('/getSalesC',[ClientController::class, 'getSalesC']);

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
        Route::get('/changeRaiz',[RestockController::class, 'changeRaiz']);
        Route::get('/getSupply/{sid}',[RestockController::class, 'getSupply']);
        Route::get('/getVerified/{sid}',[RestockController::class, 'getVerified']);
        Route::get('/getChof/{sid}',[RestockController::class, 'getChof']);
        Route::get('/getInvoices',[RestockController::class, 'getInvoices']);
        Route::get('/AutomateRequisition',[RestockController::class, 'AutomateRequisition']);
        Route::get('/getStores',[RestockController::class, 'getStores']);
        Route::get('/getCheck/{cli}',[RestockController::class, 'getCheck']);
        Route::post('/saveSupply',[RestockController::class, 'saveSupply']);
        Route::post('/createParitions',[RestockController::class, 'createParitions']);
        Route::post('/saveVerified',[RestockController::class, 'saveVerified']);
        Route::post('/saveChofi',[RestockController::class, 'saveChofi']);
        Route::post('/saveReceipt',[RestockController::class, 'saveReceipt']);
        Route::post('/saveCheck',[RestockController::class, 'saveCheck']);
        Route::post('/getSalida',[RestockController::class, 'getSalida']);
        Route::post('/getTransfer',[RestockController::class, 'getTransfer']);
        Route::post('/getSupplier',[RestockController::class, 'getSupplier']);
        Route::post('/changeStatus',[RestockController::class, 'changeStatus']);
        Route::post('/sendMessage',[RestockController::class, 'sendMessages']);
        Route::post('/getData',[RestockController::class, 'getData']);
        Route::post('/refresTransit',[RestockController::class, 'refresTransit']);
        Route::post('/deletePartition',[InvoicesController::class, 'deletePartition']);

    });

    Route::prefix('/sales')->group(function(){
        Route::get('/getSale',[SalesController::class, 'Index']);
        Route::get('/getStores',[SalesController::class, 'getStores']);
        Route::get('/generate',[SalesController::class, 'generate']);
        Route::post('/getSale',[SalesController::class, 'getSale']);
    });


    Route::prefix('/prints')->group(function(){
        Route::post('/PrintAttention',[PrinterController::class, 'PrintAttention']);
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
        Route::post('/getOrderCash',[CashController::class,'getOrderCash']);
        Route::post('/getPartitionCash',[CashController::class,'getPartitionCash']);
        Route::post('/getPresupuestoCash',[CashController::class,'getPresupuestoCash']);
        Route::post('/getticketCash',[CashController::class,'getticketCash']);
    });

    Route::prefix('/invoices')->group(function(){
        Route::get('/index',[InvoicesController::class,'index']);
        Route::get('/getStoresAutomate',[InvoicesController::class,'getStoresAutomate']);
        Route::get('/{oid}',[InvoicesController::class,'order']);
        Route::get('/{oid}/newinvoice',[InvoicesController::class,'newinvoice']);
        Route::get('/{oid}/newTransfer',[InvoicesController::class,'newTransfer']);
        Route::get('/{oid}/fresh', [InvoicesController::class,'orderFresh']);
        Route::get('/{pid}/partitionFresh', [InvoicesController::class,'partitionFresh']);
        Route::get('/getInvoice/{inv}',[InvoicesController::class,'getInvoice']);
        Route::post('/addInvoice',[InvoicesController::class,'addInvoice']);
        Route::post('/addProduct',[InvoicesController::class,'addProduct']);
        Route::post('/checkin',[InvoicesController::class,'checkin']);
        Route::post('/editProduct',[InvoicesController::class,'editProduct']);
        Route::post('/deleteProduct',[InvoicesController::class,'deleteProduct']);
        Route::post('/changestate',[InvoicesController::class,'changestate']);
        Route::get('/report/{rep}', [InvoicesController::class,'report']);
        Route::post('/massaction',[InvoicesController::class,'massaction']);
        Route::post('/addInvoiceFS',[InvoicesController::class,'addInvoiceFS']);
        Route::post('/addTransferFS',[InvoicesController::class,'addTransferFS']);
        Route::post('/endTransferFS',[InvoicesController::class,'endTransferFS']);
        Route::post('/addEntryFS',[InvoicesController::class,'addEntryFS']);
        Route::post('/indexDashboard',[InvoicesController::class,'indexDashboard']);
        Route::post('/print/forsupply',[InvoicesController::class,'printforsupply']);
        Route::post('/print/Partition',[InvoicesController::class,'pritnforPartition']);
        Route::post('/changestateRequisition',[InvoicesController::class,'changestateRequisition']);
        Route::post('/setdelivery',[InvoicesController::class,'setdelivery']);
        Route::post('/setreceived',[InvoicesController::class,'setreceived']);
        Route::post('/correction',[InvoicesController::class,'correction']);
        Route::post('/sendMessageDiff',[InvoicesController::class,'sendMessageDiff']);
    });

    Route::prefix('/invoicesReceived')->group(function(){
        Route::get('/',[InvoicesReceived::class, 'getInvoices']);
        Route::get('/replyInvoices',[InvoicesReceived::class, 'replyInvoices']);
        Route::get('/updateInvoices',[InvoicesReceived::class, 'updateInvoices']);
    });

    Route::prefix('/withdrawalStore')->group(function(){
        Route::get('/{sid}',[WithdrawalsController::class, 'getWithdrawalsStore']);
    });

    Route::prefix('/locations')->group(function(){
        Route::get('/{sid}',[locationController::class, 'index']);
        Route::get('/getInit/{sid}',[locationController::class, 'getInit']);
        Route::post('/obtProductSections',[locationController::class, 'obtProductSections']);
        Route::post('/obtProduct',[locationController::class, 'obtProduct']);
        Route::post('/obtProductCategories',[locationController::class, 'obtProductCategories']);
        Route::post('/obtSections',[locationController::class, 'obtSections']);
        Route::post('/insertSection',[locationController::class, 'insertSection']);
        Route::post('/addLocations',[locationController::class, 'addLocations']);
        Route::post('/deleteSection',[locationController::class, 'deleteSection']);
        Route::post('/deleteSectionProducts',[locationController::class, 'deleteSectionProducts']);
        Route::post('/deleteCategoriesLocations',[locationController::class, 'deleteCategoriesLocations']);
        Route::post('/addMassiveLocation',[locationController::class, 'addMassiveLocation']);
        Route::post('/deleteMassiveLocation',[locationController::class, 'deleteMassiveLocation']);
    });
});
