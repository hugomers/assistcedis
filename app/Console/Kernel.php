<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Http\Controllers\CashController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\AssistController;
use App\Http\Controllers\InvoicesReceived;




class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->call(function () {
            $controller = new CashController();
            $controller->RepliedSales();
        })->everyFifteenMinutes()->between('7:00', '23:59')->name("REPLICADOR DE VENTAS :)");//Respaldo solo de el ejercico actual

        $schedule->call(function () {
            $controller = new SalesController();
            $controller->generate();
        })->dailyAt('19:00')->name("ENVIO REPORTE DE VENTAS :)");//Respaldo solo de el ejercico actual

        $schedule->call(function () {
            $controller = new InvoicesReceived();
            $controller->replyInvoices();
        })->dailyAt('20:00')->name("Replicacion de Compras :)");//Respaldo solo de el ejercico actual


            $schedule->call(function () {
            // \Log::info('Tarea finalizada. s');
            $controller = new AssistController();
            $controller->ReplyAssistAut();
            // \Log::info('Tarea finalizada. s');
        })
        ->everyTenMinutes()
        // ->everyMinute()
        ->between('09:00', '11:00')
        ->name("Replicacion de asistencia cada 5 min");

        $schedule->call(function () {
            $controller = new AssistController();
            $controller->ReplyAssistAut();
        })->everyTwoHours($minutes = 0)->between('11:30', '20:30')->name("Replicacion de asistencia cada 2 horas");


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
