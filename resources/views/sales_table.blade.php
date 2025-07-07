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
            border: 1px solid black;
            padding: 5px 10px;
            text-align: left;
        }
        th {
            background-color: #0070C0;
            color: white;
        }
        tfoot td {
            background-color: yellow;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <table>
        <thead>
            <tr><th>Sucursal</th><th>Total</th><th>Tickets</th></tr>
        </thead>
        <tbody>
            @foreach ($data as $item)
            <tr>
                <td>{{ $item['sucursal'] }}</td>
                <td>${{ number_format($item['total'], 2) }}</td>
                <td>{{ $item['tickets'] }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>Total</td>
                <td>${{ number_format(array_sum(array_column($data, 'total')), 2) }}</td>
                <td>{{ array_sum(array_column($data, 'tickets')) }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
