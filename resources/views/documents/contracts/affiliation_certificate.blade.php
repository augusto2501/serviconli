<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Certificado de afiliación — Serviconli</title>
    @include('documents.contracts.partials.styles')
</head>
<body>
@include('documents.contracts.partials.doc_header', ['title' => 'Certificado de afiliación'])
<h2>Certificación</h2>
<p>Se certifica que <strong>{{ $personName }}</strong>, identificado(a) como se indicó al pie de datos, figura en el sistema Serviconli con número de registro de afiliado <strong>#{{ $affiliateId }}</strong>, en el estado reportado al momento de la generación de este certificado.</p>
<p>Este certificado es de carácter informativo y no reemplaza los registros oficiales ante entidades del Sistema de Seguridad Social.</p>
<div class="footer">Documento modelo RF-103 — certificado de afiliación.</div>
</body>
</html>
