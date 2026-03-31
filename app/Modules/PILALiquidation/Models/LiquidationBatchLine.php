<?php

namespace App\Modules\PILALiquidation\Models;

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliations\Models\SocialSecurityProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea individual dentro de un lote de liquidación.
 *
 * Cada fila = un afiliado × un período × cálculo completo de SS.
 *
 * @see DOCUMENTO_RECTOR §4 Grupo D — pay_liquidation_batch_lines
 */
class LiquidationBatchLine extends Model
{
    protected $table = 'pay_liquidation_batch_lines';

    protected $fillable = [
        'batch_id',
        'affiliate_id',
        'ss_profile_id',
        'ibc',
        'ibc2',
        'salary',
        'days_eps',
        'days_afp',
        'days_arl',
        'days_ccf',
        'health_employer',
        'health_employee',
        'health_total',
        'pension_employer',
        'pension_employee',
        'pension_total',
        'arl_total',
        'ccf_total',
        'solidarity',
        'upc',
        'admin_fee',
        'affiliation_fee',
        'interest_mora',
        'total_ss',
        'total_payable',
        'contributor_type_code',
        'occupation_code_768',
        'subtipo',
        'novelties',
        'retirement_scope',
        'service_code',
        'payment_method',
        'has_exception',
        'exception_id',
        'line_status',
    ];

    protected function casts(): array
    {
        return [
            'novelties' => 'array',
            'has_exception' => 'boolean',
        ];
    }

    /** @return BelongsTo<LiquidationBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(LiquidationBatch::class, 'batch_id');
    }

    /** @return BelongsTo<Affiliate, $this> */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /** @return BelongsTo<SocialSecurityProfile, $this> */
    public function socialSecurityProfile(): BelongsTo
    {
        return $this->belongsTo(SocialSecurityProfile::class, 'ss_profile_id');
    }

    public function isIncluded(): bool
    {
        return $this->line_status === 'INCLUIDO';
    }

    public function isExcluded(): bool
    {
        return $this->line_status === 'EXCLUIDO';
    }
}
