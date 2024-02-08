<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Support\Facades\DB;
// use App\Http\Controllers\AssistController;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Style;
use PhpOffice\PhpSpreadsheet\Style\Color;
// use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeExport;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;



class AssistExport implements FromCollection, WithHeadings, ShouldAutoSize, WithEvents
{
    // /**
    // * @return \Illuminate\Support\Collection
    // */
    public function collection(){
        // return new AssistController();
        $semana = now()->format('W') - 1;
        $anio = now()->format('Y');

        $staffData = DB::select('call report_assist('.$semana.','.$anio.')');

        $dataForExport = collect($staffData)->map(function ($fila) {
            return [
                "año" => $fila->AÑO,
                "semana" => $fila->SEMANA,
                "id" => $fila->ID,
                "nombre" => $fila->NOMBRE,
                "sucursal" => $fila->SUCURSAL,
                "entrada" => $fila->ENTRADA,
                "lunes" => $fila->LUNES,
                "martes" => $fila->MARTES,
                "miercoles" => $fila->MIERCOLES,
                "jueves" => $fila->JUEVES,
                "viernes" => $fila->VIERNES,
                "sabado" => $fila->SABADO,
                "domingo" => $fila->DOMINGO,
                "faltas" => $fila->FALTA,
                "retardos"=>$fila->RETARDOS,
            ];
        });

        return $dataForExport;
    }
    public function headings(): array{
        return [
            "AÑO",
            "SEMANA",
            "ID",
            "NOMBRE",
            "SUCURSAL",
            "ENTRADA",
            "LUNES",
            "MARTES",
            "MIERCOLES",
            "JUEVES",
            "VIERNES",
            "SABADO",
            "DOMINGO",
            "FALTAS",
            "RETARDOS"
        ];
    }
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $totalRows = 500;
                $fil = ["G","H","I","J","K","L","M"];
                foreach($fil as $column){
                    for ($row = 2; $row <= $totalRows + 1; $row++) {
                        $cellValue = $event->sheet->getCell($column . $row)->getValue();

                        if(strpos($cellValue, 'R') !== false) {
                            // Apply bold font to cell A in the current row
                            $event->sheet->getStyle($column . $row)->applyFromArray([
                                'font' => [
                                    'color'=>['rgb'=>Color::COLOR_RED],
                                ],
                                'fill'=>[
                                    'fillType' =>Fill::FILL_SOLID,
                                    'startColor' =>['rgb'=>Color::COLOR_YELLOW]
                                ]
                            ]);
                        }else if(strpos($cellValue,'FALTA') !== false){
                            $event->sheet->getStyle($column . $row)->applyFromArray([
                            'font' => [
                                'color'=>['rgb'=>'9C0006'],
                            ],
                            'fill'=>[
                                'fillType' =>Fill::FILL_SOLID,
                                'startColor' =>['rgb'=>'FFC7CE']
                            ]
                        ]);
                        }else if(strpos($cellValue,'-0%')!== false){
                            $event->sheet->getStyle($column . $row)->applyFromArray([
                            'font' => [
                                'color'=>['rgb'=>'9C0006'],
                            ],
                            'fill'=>[
                                'fillType' =>Fill::FILL_SOLID,
                                'startColor' =>['rgb'=>'FFC7CE']
                            ]
                        ]);
                        }else if(strpos($cellValue,'-100%') !== false){
                            $event->sheet->getStyle($column . $row)->applyFromArray([
                                'font' => [
                                    'color'=>['rgb'=>'203764'],
                                ],
                                'fill'=>[
                                    'fillType' =>Fill::FILL_SOLID,
                                    'startColor' =>['rgb'=>'BDD7EE']
                                ]
                            ]);
                        }else if(strpos($cellValue,'-50%')  !== false ){
                            $event->sheet->getStyle($column . $row)->applyFromArray([
                                'font' => [
                                    'color'=>['rgb'=>'375623'],
                                ],
                                'fill'=>[
                                    'fillType' =>Fill::FILL_SOLID,
                                    'startColor' =>['rgb'=>'C6E0B4']
                                ]
                            ]);
                        }else if(strpos($cellValue,'DESCANSO')  !== false){
                            $event->sheet->getStyle($column . $row)->applyFromArray([
                                'font' => [
                                    'color'=>['rgb'=>'833C0C'],
                                ],
                                'fill'=>[
                                    'fillType' =>Fill::FILL_SOLID,
                                    'startColor' =>['rgb'=>'FCE4D6']
                                ]
                            ]);
                        }
                    }
                }
            },
        ];
    }
}

