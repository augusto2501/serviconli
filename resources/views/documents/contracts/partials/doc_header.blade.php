@php
    /** @var string $title */
    /** @var int $templateVersion */
    /** @var \Illuminate\Support\Carbon $generatedAt */
@endphp
<div class="header">
    <h1>Serviconli — {{ $title }}</h1>
    <p class="meta">Plantilla v{{ $templateVersion }} · Generado {{ $generatedAt->format('d/m/Y H:i') }}</p>
</div>
<p><strong>Afiliado:</strong> {{ $personName }}</p>
@if($documentNumber !== '')
    <p><strong>Documento:</strong> {{ $documentNumber }}</p>
@endif
@if(isset($statusCode) && $statusCode !== null && $statusCode !== '')
    <p><strong>Estado afiliación:</strong> {{ $statusCode }}</p>
@endif
