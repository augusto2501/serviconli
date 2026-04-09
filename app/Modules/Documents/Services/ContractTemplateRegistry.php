<?php

namespace App\Modules\Documents\Services;

/**
 * RF-103 — catálogo de plantillas de contrato y versión vigente (v1).
 *
 * Versionado: al publicar v2, añadir fila y mantener vistas v1 para documentos ya firmados (iteración futura).
 */
final class ContractTemplateRegistry
{
    public const VERSION = 1;

    /** @var array<string, array{version: int, view: string}> */
    private const TEMPLATES = [
        'operational_clauses' => ['version' => 1, 'view' => 'documents.contracts.operational_clauses'],
        'legal_association' => ['version' => 1, 'view' => 'documents.contracts.legal_association'],
        'affiliate_declaration' => ['version' => 1, 'view' => 'documents.contracts.affiliate_declaration'],
        'affiliation_type_declaration' => ['version' => 1, 'view' => 'documents.contracts.affiliation_type_declaration'],
        'voluntary_withdrawal' => ['version' => 1, 'view' => 'documents.contracts.voluntary_withdrawal'],
        'affiliation_certificate' => ['version' => 1, 'view' => 'documents.contracts.affiliation_certificate'],
        'payment_certificate' => ['version' => 1, 'view' => ''], // resuelto por formato full/summary
    ];

    public static function codes(): array
    {
        return array_keys(self::TEMPLATES);
    }

    public static function viewFor(string $code): ?string
    {
        return self::TEMPLATES[$code]['view'] ?? null;
    }

    public static function versionFor(string $code): int
    {
        return self::TEMPLATES[$code]['version'] ?? self::VERSION;
    }

    public static function paymentCertificateView(string $format): string
    {
        return $format === 'summary'
            ? 'documents.contracts.payment_certificate_summary'
            : 'documents.contracts.payment_certificate_full';
    }
}
