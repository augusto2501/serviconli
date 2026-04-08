<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Certificado de pago PILA — Serviconli</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; padding: 28px; }
        .header { border-bottom: 2px solid #1e40af; padding-bottom: 12px; margin-bottom: 20px; }
        .header h1 { font-size: 16px; color: #1e40af; }
        .header p { color: #555; font-size: 10px; margin-top: 4px; }
        h2 { font-size: 12px; color: #1e40af; margin: 16px 0 8px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { padding: 6px 8px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #1e40af; color: #fff; font-size: 10px; }
        .total { font-weight: bold; background: #dbeafe; }
        .footer { margin-top: 28px; font-size: 9px; color: #666; border-top: 1px solid #e5e7eb; padding-top: 10px; text-align: center; }
    </style>
</head>
<body>

<div class="header">
    <h1>Serviconli — Certificado de pago (PILA)</h1>
    <p>RN-22 · Expedición: {{ now()->format('d/m/Y H:i') }}</p>
</div>

<p><strong>Afiliado:</strong> {{ $personName }}</p>
@if($documentNumber !== '')
    <p><strong>Documento:</strong> {{ $documentNumber }}</p>
@endif

<h2>Período cotizado</h2>
<p>{{ sprintf('%04d-%02d', $period['year'], $period['month']) }}</p>

<h2>Valores registrados</h2>
<table>
    <thead>
        <tr>
            <th>Concepto</th>
            <th>Valor</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>IBC redondeado</td>
            <td>$ {{ number_format($line['ibcRoundedPesos'] ?? 0, 0, ',', '.') }}</td>
        </tr>
        <tr class="total">
            <td>Total seguridad social</td>
            <td>$ {{ number_format($line['totalSocialSecurityPesos'] ?? 0, 0, ',', '.') }}</td>
        </tr>
    </tbody>
</table>

<p style="margin-top: 16px;"><em>{{ $message }}</em></p>

<div class="footer">
    Documento informativo basado en liquidación PILA confirmada en el sistema Serviconli.
</div>

</body>
</html>
