<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $document['remission']->folio ?: 'Remision' }}</title>
</head>
<body style="margin:0;">
    @include('comercial.remisiones._invoice', ['document' => $document])
</body>
</html>
