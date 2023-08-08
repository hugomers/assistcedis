<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <style>
        h1{
            text-align: center;
        }
        body {
            font-family: Arial, sans-serif;

            background-color: white;
        }
        .static {
            font-size: 10pt;
        }
        .res {
            font-size: 9pt;
        }
        .logo {
            position: absolute;
            top: 0.05in;
            left: 0;
            width: 1.5in;
            height: auto;
        }
        p {
            /* margin-bottom: -.05em; */
            margin:5px 0;
            padding:0;
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.2;
            width: 7.0in;
        }
    </style>
    <title>Actas aministrativas</title>
</head>
<body>
    @if($sucursal == "PUEBLA")
    <img class = "logo"  src="{{asset('img/candytos.png')}}">
    <img class = "watermark"  src="{{asset('img/candytos.png')}}">
    @else
    <img class = "logo"  src="{{asset('img/tipelog.png')}}">
    <img class = "watermark"  src="{{asset('img/tipelog.png')}}">
    @endif
    <p></p>
    <h1>ACTA ADMINISTRATIVA</h1>
    <p></p>
    <p class = "static">En la Ciudad de México, siendo las <strong>{{$hora}}</strong> horas del día  <strong>{{$fecha}}</strong>
        se levanta la presente acta administrativa en las instalaciones de la sucursal <strong>{{$sucursal}}</strong>
        ubicada en <strong>{{$domicilio}}</strong>, a el (la) C.
        <strong>{{$nombre}}</strong> quien ocupa el puesto de <strong>{{$puesto}}</strong>
        derivado de los siguientes hechos a continuación relatados:
    </p>

    <p class = "res" style="word-wrap: break-word;">{{$motivo}}</p>

    <p class = "static">En este momento se le da la palabra a el (la) colaborador (a) para que manifieste lo que a su derecho convenga:</p>


    @if($defensacol == null)
        <p>_______________________________________________________________________________</p>
        <p>_______________________________________________________________________________</p>
        <p>_______________________________________________________________________________</p>
        <p>_______________________________________________________________________________</p>
    @else
        <p class = "res" style="word-wrap: break-word;">{{$defensacol}}</p>
    @endif

    <p class = "static">Asimismo se le solicita a los participantes en la elaboración de esta acta administrativa desean agregar algo,
        siendo su respuesta:
    </p>

    @if($respuestacompa == null)
        <p>_______________________________________________________________________________</p>
        <p>_______________________________________________________________________________</p>
        <p>_______________________________________________________________________________</p>
        <p>_______________________________________________________________________________</p>
    @else
        <p class = "res" style="word-wrap: break-word;">{{$respuestacompa}}</p>
    @endif


    <p class = "static">Derivado de lo anterior se concluye que:</p>

    <p class = "res" style="word-wrap: break-word;">{{$conclusion}}</p>

    <p class = "static">Firman de mutuo acuerdo los testigos y el (la) colaborador (a) dando así por terminada la actual acta
        administrativa, ratificando cada una de sus partes y la participación de el (la) colaborador (a) señalado (a).
    </p>

    <div style="margin-top:75px" >
        <div style="display: inline-block; width: 25%; border-top: 1px solid black; text-align: center ; margin-right: 60px;">COLABORADOR (A)</div>
        <div style="display: inline-block; width: 25%; border-top: 1px solid black; text-align: center ;  margin-right: 60px;">JEFE INMEDIATO</div>
        <div style="display: inline-block; width: 25%; border-top: 1px solid black; text-align: center ;">RECURSOS HUMANOS</div>
    </div>

    <div style=" text-aline: center; margin-top:75px">
        <div style="display: inline-block; width: 25%; border-top: 1px solid black; text-align: center ; margin-right: 60px;">GERENCIA GENERAL</div>
        <div style="display: inline-block; width: 25%; border-top: 1px solid black; text-align: center ;  margin-right: 60px;">TESTIGO 1 </div>
        <div style="display: inline-block; width: 25%; border-top: 1px solid black; text-align: center ;">TESTIGO 2 </div>
    </div>
</body>
</html>
