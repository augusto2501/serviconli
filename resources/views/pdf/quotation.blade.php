<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotización Seguridad Social — Serviconli</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #222;
            padding: 24px;
        }
        .header {
            border-bottom: 2px solid #1e40af;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 18px;
            color: #1e40af;
            letter-spacing: 1px;
        }
        .header p { color: #555; font-size: 10px; margin-top: 2px; }
        .section { margin-bottom: 18px; }
        .section h2 {
            font-size: 12px;
            text-transform: uppercase;
            color: #1e40af;
            border-bottom: 1px solid #dbeafe;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        .grid { display: table; width: 100%; }
        .row { display: table-row; }
        .cell { display: table-cell; padding: 3px 6px; }
        .cell.label { color: #555; width: 45%; }
        .cell.value { font-weight: bold; }
        table.amounts {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        table.amounts th {
            background: #1e40af;
            color: #fff;
            text-align: left;
            padding: 5px 8px;
            font-size: 10px;
        }
        table.amounts td {
            padding: 4px 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        table.amounts tr:nth-child(even) td { background: #f1f5f9; }
        .total-row td {
            font-weight: bold;
            background: #dbeafe !important;
            border-top: 2px solid #1e40af;
        }
        .footer {
            margin-top: 24px;
            font-size: 9px;
            color: #888;
            border-top: 1px solid #e5e7eb;
            padding-top: 8px;
            text-align: center;
        }
        .badge {
            display: inline-block;
            background: #1e40af;
            color: #fff;
            border-radius: 4px;
            padding: 2px 8px;
            font-size: 10px;
        }
    </style>
</head>
<body>

<div class="header">
    <h1>Serviconli &mdash; Cotización de Seguridad Social</h1>
    <p>Armenia, Quindío &bull; Fecha de expedición: {{ $quotation->created_at->format('d/m/Y H:i') }}</p>
</div>

<div class="section">
    <h2>Datos del prospecto</h2>
    <div class="grid">
        <div class="row">
            <div class="cell label">Nombre</div>
            <div class="cell value">{{ $quotation->prospect_name }}</div>
        </div>
        @if($quotation->prospect_document)
        <div class="row">
            <div class="cell label">Documento</div>
            <div class="cell value">{{ $quotation->prospect_document }}</div>
        </div>
        @endif
        @if($quotation->prospect_phone)
        <div class="row">
            <div class="cell label">Teléfono</div>
            <div class="cell value">{{ $quotation->prospect_phone }}</div>
        </div>
        @endif
        @if($quotation->prospect_email)
        <div class="row">
            <div class="cell label">Correo</div>
            <div class="cell value">{{ $quotation->prospect_email }}</div>
        </div>
        @endif
    </div>
</div>

<div class="section">
    <h2>Parámetros de cotización</h2>
    <div class="grid">
        <div class="row">
            <div class="cell label">Salario mensual</div>
            <div class="cell value">$ {{ number_format($quotation->salary_pesos, 0, ',', '.') }}</div>
        </div>
        <div class="row">
            <div class="cell label">IBC liquidado</div>
            <div class="cell value">$ {{ number_format($quotation->amounts['ibcRoundedPesos'] ?? $quotation->salary_pesos, 0, ',', '.') }}</div>
        </div>
        <div class="row">
            <div class="cell label">Tipo cotizante</div>
            <div class="cell value">{{ $quotation->contributor_type_code }}</div>
        </div>
        <div class="row">
            <div class="cell label">Clase riesgo ARL</div>
            <div class="cell value">{{ $quotation->arl_risk_class }}</div>
        </div>
    </div>
</div>

<div class="section">
    <h2>Aportes calculados</h2>
    <table class="amounts">
        <thead>
            <tr>
                <th>Subsistema</th>
                <th>Valor mensual</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lineItems as $label => $amount)
            <tr>
                <td>{{ $label }}</td>
                <td>$ {{ number_format($amount, 0, ',', '.') }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td>TOTAL SEGURIDAD SOCIAL</td>
                <td>$ {{ number_format($quotation->amounts['totalSocialSecurityPesos'] ?? 0, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>
</div>

<div class="footer">
    Cotización informativa &bull; Cálculos basados en tarifas vigentes {{ now()->format('Y') }} &bull;
    Generado por Serviconli &bull; No constituye contrato de afiliación.
</div>

</body>
</html>
