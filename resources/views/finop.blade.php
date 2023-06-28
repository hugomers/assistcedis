<!DOCTYPE html>
<html>
<head>
  <title>CHECKLIST - INICIO DE OPERACIONES</title>
  <style>
    h1, h2 {
      text-align: center;
    }
    table {
        margin: 20px auto;
        border: #b2b2b2 1px solid;
        font-size: 13px;
    }
    td, th {
    border: black 1px solid;
    text-align: center;
    }
  </style>
</head>
<body>
  <h1>CHECKLIST</h1>
  <h2>FINAL DE OPERACIONES</h2>

  <p>FECHA: <span id="fecha">{{ $fecha }}</span>
    <span style="float: right;">Puntuacion: <span id="puntuacion">{{$puntuacion}}</span></span>
  </p>
  <p>Enc. Administrativo: <span id="enc_admin">{{$admin}}</span>
    <span style="float: right;">Sucursal: <span id="sucursal">{{$sucursal}}</span></span>
  </p>


  <table>
    <thead>
      <tr>
        <th>CHECK</th>
        <th>PUNTOS</th>
        <th>PERSONAL</th>
        <th>OBSERVACION</th>
      </tr>
    </thead>
    <tbody>
        <tr>
            <td> {{$ppg1}} </td>
            <td> {{$tot1}} </td>
            <td> {{$per1}} </td>
            <td> {{$obs1}} </td>
        </tr>
        <tr>
            <td> {{$ppg2}} </td>
            <td> {{$tot2}} </td>
            <td> {{$per2}} </td>
            <td> {{$obs2}} </td>
        </tr>
        <tr>
            <td> {{$ppg3}} </td>
            <td> {{$tot3}} </td>
            <td> {{$per3}} </td>
            <td> {{$obs3}} </td>
        </tr>
        <tr>
            <td> {{$ppg4}} </td>
            <td> {{$tot4}} </td>
            <td> {{$per4}} </td>
            <td> {{$obs4}} </td>
        </tr>
        <tr>
            <td> {{$ppg5}} </td>
            <td> {{$tot5}} </td>
            <td> {{$per5}} </td>
            <td> {{$obs5}} </td>
        </tr>
        <tr>
            <td> {{$ppg6}} </td>
            <td> {{$tot6}} </td>
            <td> {{$per6}} </td>
            <td> {{$obs6}} </td>
        </tr>
        <tr>
            <td> {{$ppg7}} </td>
            <td> {{$tot7}} </td>
            <td> {{$per7}} </td>
            <td> {{$obs7}} </td>
        </tr>
        <tr>
            <td> {{$ppg8}} </td>
            <td> {{$tot8}} </td>
            <td> {{$per8}} </td>
            <td> {{$obs8}} </td>
        </tr>
        <tr>
            <td> {{$ppg9}} </td>
            <td> {{$tot9}} </td>
            <td> {{$per9}} </td>
            <td> {{$obs9}} </td>
        </tr>
        <tr>
            <td> {{$ppg10}} </td>
            <td> {{$tot10}} </td>
            <td> {{$per10}} </td>
            <td> {{$obs10}} </td>
        </tr>
        <td>TOTAL</td>
        <td colspan="3" style="text-align: center;">{{$total}}</td>
      </tr>
    </tbody>
  </table>
<br>
<br>
<br>
<br>
  <div style="margin-top: 20px;">
    <div style="float: left; width: 40%; border-top: 1px solid black; text-align: center;">FIRMA DE ENC SUCURSAL</div>
    <div style="float: right; width: 40%; border-top: 1px solid black; text-align: center;">FIRMA ENC.ADMINISTRATIVO</div>
  </div>
</body>
</html>
