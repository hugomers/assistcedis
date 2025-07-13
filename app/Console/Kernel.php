<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Http\Controllers\CashController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\InvoicesReceived;




class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->call(function () {
        //     $controller = new CashController();
        //     $controller->RepliedSales();
        // })->everyFifteenMinutes()->between('7:00', '23:59')->name("REPLICADOR DE VENTAS :)");//Respaldo solo de el ejercico actual

        $schedule->call(function () {
            $controller = new SalesController();
            $controller->generate();
        })->dailyAt('01:10')->name("ENVIO REPORTE DE VENTAS :)");//Respaldo solo de el ejercico actual

        $schedule->call(function () {
            $controller = new InvoicesReceived();
            $controller->replyInvoices();
        })->dailyAt('20:00')->name("Replicacion de Compras :)");//Respaldo solo de el ejercico actual

    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
