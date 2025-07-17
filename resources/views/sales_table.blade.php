<!DOCTYPE html>
<html>
<head>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
            font-family: Arial, sans-serif;
        }
        th, td {
            border: 1px solid #000;
            padding: 5px 10px;
            text-align: center;
        }
        th {
            background-color: #0070C0;
            color: white;
        }
        .sub-header th {
            background-color: #cfe2f3;
            color: #000;
        }
        .footer td {
            background-color: yellow;
            font-weight: bold;
        }
        .tr-error {
            background-color: red;
            color: white;
            text-align: center;
        }
        .tr-ok {
            border: 1px solid #000;
            padding: 5px 10px;
            text-align: center;
        }
    </style>
</head>
<body>
    <table>
        <thead>
            <tr>
                <th rowspan="2">SUCURSAL</th>
                <th rowspan="2">VENTA</th>
                <th rowspan="2">TICKETS</th>
                <th colspan="5">DESGLOSE</th>

            </tr>
            <tr class="sub-header">
                <th>EFECTIVO</th>
                <th>TARJETAS</th>
                <th>TRANSFEREN</th>
                <th>CREDITO</th>
                <th>VALE</th>

            </tr>
        </thead>
        <tbody>
            @php
                $total_venta = 0;
                $total_tickets = 0;
                $total_efectivo = 0;
                $total_tarjetas = 0;
                $total_transferencia = 0;
                $total_credito = 0;
                $total_vale = 0;
            @endphp
            @foreach ($data as $item)
                @php
                    $efectivo = 0;
                    $tarjetas = 0;
                    $transferencia = 0;
                    $credito = 0;
                    $vale = 0;
                    foreach ($item['desglose'] as $pago) {
                        switch ($pago['FORMAPAGO']) {
                            case 'EFE': $efectivo += $pago['TOTAL']; break;
                            case 'TDB':
                            case 'TDA':
                            case 'TDS':
                                $transferencia += $pago['TOTAL']; break;
                            case 'TSA':
                            case 'TBA':
                            case 'TSC':
                                $tarjetas += $pago['TOTAL']; break;
                            case 'C30': $credito += $pago['TOTAL']; break;
                            case '[V]': $vale += $pago['TOTAL']; break;
                        }
                    }
                    $total_venta += $item['total'];
                    $total_tickets += $item['tickets'];
                    $total_efectivo += $efectivo;
                    $total_tarjetas += $tarjetas;
                    $total_transferencia += $transferencia;
                    $total_credito += $credito;
                    $total_vale += $vale;
                @endphp

                <tr class="{{ $item['status'] ? '' : 'tr-error' }} ;">
                    <td>{{ $item['sucursal'] }}</td>
                    <td>${{ number_format($item['total'], 2) }}</td>
                    <td>{{ number_format($item['tickets'], 2) }}</td>
                    <td>${{ number_format($efectivo, 2) }}</td>
                    <td>${{ number_format($tarjetas, 2) }}</td>
                    <td>${{ number_format($transferencia, 2) }}</td>
                    <td>${{ number_format($credito, 2) }}</td>
                    <td>${{ number_format($vale, 2) }}</td>
                </tr>
            @endforeach
        </tbody>

        <tfoot class="footer">
            <tr class="tr-ok">
                <td>TOTALES</td>
                <td>${{ number_format($total_venta, 2) }}</td>
                <td>{{ number_format($total_tickets, 2) }}</td>
                <td>${{ number_format($total_efectivo, 2) }}</td>
                <td>${{ number_format($total_tarjetas, 2) }}</td>
                <td>${{ number_format($total_transferencia, 2) }}</td>
                <td>${{ number_format($total_credito, 2) }}</td>
                <td>${{ number_format($total_vale, 2) }}</td>
            </tr>
        </tfoot>

    </table>
</body>
</html>
