<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Certificado de pago (completo) — Serviconli</title>
    @include('documents.contracts.partials.styles')
</head>
<body>

<div class="header">
    <h1>Serviconli — Certificado de pago PILA (formato completo)</h1>
    <p class="meta">RF-103 · v1 · Expedición: {{ $generatedAt->format('d/m/Y H:i') }}</p>
</div>

<p><strong>Afiliado:</strong> {{ $personName }}</p>
@if($documentNumber !== '')
    <p><strong>Documento:</strong> {{ $documentNumber }}</p>
@endif

<h2>Período cotizado</h2>
<p>{{ sprintf('%04d-%02d', $period['year'], $period['month']) }}</p>

<h2>Detalle de valores</h2>
<table>
    <thead>
        <tr>
            <th>Concepto</th>
            <th>Valor ($)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>IBC redondeado</td>
            <td>{{ number_format($line['ibcRoundedPesos'] ?? 0, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Total seguridad social</td>
            <td><strong>{{ number_format($line['totalSocialSecurityPesos'] ?? 0, 0, ',', '.') }}</strong></td>
        </tr>
        @if(isset($line['daysLate']) && $line['daysLate'] !== null)
            <tr>
                <td>Días de mora (referencia línea)</td>
                <td>{{ $line['daysLate'] }}</td>
            </tr>
        @endif
    </tbody>
</table>

<p style="margin-top: 14px;"><em>{{ $message }}</em></p>

<div class="footer">
    Documento informativo basado en liquidación PILA confirmada. Formato completo RF-103.
</div>

</body>
</html>
