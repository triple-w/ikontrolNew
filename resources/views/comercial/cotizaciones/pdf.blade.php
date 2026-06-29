<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $document['quote']->folio ?: 'Cotizacion' }}</title>
</head>
<body style="margin:0;">
    @include('comercial.documentos._invoice', ['document' => $document])
</body>
</html>
