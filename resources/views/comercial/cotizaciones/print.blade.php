<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document['quote']->folio ?: 'Cotizacion' }}</title>
    <style>
        body { margin: 24px; background: #fff; }
        .no-print { margin-bottom: 16px; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Imprimir</button>
    @include('comercial.documentos._invoice', ['document' => $document])
</body>
</html>
