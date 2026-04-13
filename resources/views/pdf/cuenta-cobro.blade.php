<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cuenta de Cobro {{ $cuenta->cuenta_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #222; padding: 24px; }
        .header { border-bottom: 2px solid #1e40af; padding-bottom: 12px; margin-bottom: 16px; }
        .header h1 { font-size: 18px; color: #1e40af; }
        .header p { color: #555; font-size: 10px; margin-top: 2px; }
        .meta { margin-bottom: 16px; }
        .meta table { width: 100%; }
        .meta td { padding: 2px 6px; }
        .meta .label { color: #555; width: 30%; }
        .meta .value { font-weight: bold; }
        table.details { width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 10px; }
        table.details th { background: #1e40af; color: #fff; padding: 5px 4px; text-align: right; }
        table.details th:first-child { text-align: left; }
        table.details td { padding: 4px; border-bottom: 1px solid #e5e7eb; text-align: right; }
        table.details td:first-child { text-align: left; }
        table.details tr:nth-child(even) { background: #f9fafb; }
        .totals { width: 50%; margin-left: auto; margin-bottom: 16px; }
        .totals td { padding: 3px 6px; }
        .totals .label { text-align: right; color: #555; }
        .totals .value { font-weight: bold; text-align: right; }
        .totals .grand { font-size: 13px; color: #1e40af; border-top: 2px solid #1e40af; }
        .words { font-style: italic; color: #555; margin-bottom: 16px; font-size: 10px; }
        .dates { margin-bottom: 16px; }
        .dates td { padding: 2px 6px; }
        .footer { border-top: 1px solid #ddd; padding-top: 10px; font-size: 9px; color: #888; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>SERVICONLI S.A.S.</h1>
        <p>NIT 900966567-4 &bull; Armenia, Quindío &bull; Intermediario de Seguridad Social</p>
    </div>

    <h2 style="font-size:14px; margin-bottom:12px;">CUENTA DE COBRO N° {{ $cuenta->cuenta_number }}</h2>

    <div class="meta">
        <table>
            <tr><td class="label">Pagador:</td><td class="value">{{ $payer->razon_social ?? 'N/A' }}</td></tr>
            <tr><td class="label">NIT:</td><td class="value">{{ $payer->nit_body ?? '' }}-{{ $payer->digito_verificacion ?? '' }}</td></tr>
            <tr><td class="label">Período:</td><td class="value">{{ sprintf('%04d-%02d', $cuenta->period_year, $cuenta->period_month) }}</td></tr>
            <tr><td class="label">Modo:</td><td class="value">{{ $cuenta->generation_mode }}</td></tr>
            <tr><td class="label">Estado:</td><td class="value">{{ $cuenta->status }}</td></tr>
        </table>
    </div>

    <table class="details">
        <thead>
            <tr>
                <th>Afiliado</th>
                <th>Salud</th>
                <th>Pensión</th>
                <th>ARL</th>
                <th>CCF</th>
                <th>Admin</th>
                <th>Afiliación</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($details as $d)
            <tr>
                <td>{{ $d->affiliate?->person?->first_name ?? '' }} {{ $d->affiliate?->person?->first_surname ?? '' }}</td>
                <td>${{ number_format($d->health_pesos, 0, ',', '.') }}</td>
                <td>${{ number_format($d->pension_pesos, 0, ',', '.') }}</td>
                <td>${{ number_format($d->arl_pesos, 0, ',', '.') }}</td>
                <td>${{ number_format($d->ccf_pesos, 0, ',', '.') }}</td>
                <td>${{ number_format($d->admin_pesos, 0, ',', '.') }}</td>
                <td>${{ number_format($d->affiliation_pesos, 0, ',', '.') }}</td>
                <td>${{ number_format($d->total_pesos, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td class="label">EPS:</td><td class="value">${{ number_format($cuenta->total_eps, 0, ',', '.') }}</td></tr>
        <tr><td class="label">AFP:</td><td class="value">${{ number_format($cuenta->total_afp, 0, ',', '.') }}</td></tr>
        <tr><td class="label">ARL:</td><td class="value">${{ number_format($cuenta->total_arl, 0, ',', '.') }}</td></tr>
        <tr><td class="label">CCF:</td><td class="value">${{ number_format($cuenta->total_ccf, 0, ',', '.') }}</td></tr>
        <tr><td class="label">Administración:</td><td class="value">${{ number_format($cuenta->total_admin, 0, ',', '.') }}</td></tr>
        <tr><td class="label">Afiliación:</td><td class="value">${{ number_format($cuenta->total_affiliation, 0, ',', '.') }}</td></tr>
        <tr class="grand"><td class="label">TOTAL OPORTUNO:</td><td class="value">${{ number_format($cuenta->total_1, 0, ',', '.') }}</td></tr>
        @if($cuenta->total_2)
        <tr><td class="label">Intereses mora:</td><td class="value">${{ number_format($cuenta->interest_mora, 0, ',', '.') }}</td></tr>
        <tr class="grand"><td class="label">TOTAL CON MORA:</td><td class="value">${{ number_format($cuenta->total_2, 0, ',', '.') }}</td></tr>
        @endif
    </table>

    @if($totalWords)
    <p class="words">Son: {{ $totalWords }}</p>
    @endif

    @if($cuenta->payment_date_1 || $cuenta->payment_date_2)
    <div class="dates">
        <table>
            @if($cuenta->payment_date_1)<tr><td class="label" style="color:#555">Fecha pago oportuno:</td><td>{{ $cuenta->payment_date_1->format('d/m/Y') }}</td></tr>@endif
            @if($cuenta->payment_date_2)<tr><td class="label" style="color:#555">Fecha pago con mora:</td><td>{{ $cuenta->payment_date_2->format('d/m/Y') }}</td></tr>@endif
        </table>
    </div>
    @endif

    <div class="footer">
        Generado por Serviconli &bull; {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
