<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Certificado de pago (resumido) — Serviconli</title>
    @include('documents.contracts.partials.styles')
</head>
<body>

<div class="header">
    <h1>Serviconli — Certificado de pago (resumido)</h1>
    <p class="meta">RF-103 · v1 · {{ $generatedAt->format('d/m/Y H:i') }}</p>
</div>

<p><strong>{{ $personName }}</strong> — {{ sprintf('%04d-%02d', $period['year'], $period['month']) }}</p>
<p><strong>Total SS:</strong> $ {{ number_format($line['totalSocialSecurityPesos'] ?? 0, 0, ',', '.') }}</p>

<div class="footer">Resumen RF-103. Ver formato completo para detalle adicional.</div>

</body>
</html>
