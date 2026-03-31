<?php

namespace App\Modules\PILALiquidation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación del formulario de aporte individual — Flujo 3.
 *
 * RN-25: Días < 30 sin novedad = error (excepto tipo 41).
 * RN-27: Período duplicado = error.
 * RF-056: Días < 30 sólo con novedad ING o RET.
 * RF-057: Período ya pagado = error.
 * RF-058: Tipo 51 → días válidos: 7, 14, 21, 30.
 *
 * @see DOCUMENTO_RECTOR §5.1, RF-055..RF-060
 */
final class StoreContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'affiliate_id' => ['required', 'integer', 'exists:afl_affiliates,id'],
            'period_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
            'salary_pesos' => ['required', 'integer', 'min:1'],
            'days_eps' => ['required', 'integer', 'min:1', 'max:30'],
            'days_afp' => ['nullable', 'integer', 'min:0', 'max:30'],
            'days_arl' => ['nullable', 'integer', 'min:0', 'max:30'],
            'days_ccf' => ['nullable', 'integer', 'min:0', 'max:30'],
            'contributor_type_code' => ['required', 'string', 'max:3'],
            'subtipo' => ['nullable', 'integer', 'min:0'],
            'arl_risk_class' => ['required', 'integer', 'min:1', 'max:5'],
            'payment_method' => ['required', 'string', 'in:EFECTIVO,CONSIGNACION,CREDITO,CUENTA_COBRO'],
            'admin_fee_pesos' => ['nullable', 'integer', 'min:0'],

            // Novedades opcionales
            'novelties' => ['nullable', 'array'],
            'novelties.*.type_code' => ['required_with:novelties', 'string', 'max:3'],
            'novelties.*.start_date' => ['nullable', 'date'],
            'novelties.*.end_date' => ['nullable', 'date'],
            'novelties.*.retirement_scope' => ['nullable', 'string', 'in:TOTAL,PENSION_ONLY,ARL_ONLY'],
            'novelties.*.retirement_cause' => ['nullable', 'string', 'max:50'],
            'novelties.*.new_entity_id' => ['nullable', 'integer'],
            'novelties.*.previous_entity_id' => ['nullable', 'integer'],
            'novelties.*.new_value' => ['nullable', 'integer'],
            'novelties.*.notes' => ['nullable', 'string', 'max:500'],

            // Consignación
            'bank_name' => ['nullable', 'required_if:payment_method,CONSIGNACION', 'string', 'max:100'],
            'bank_reference' => ['nullable', 'required_if:payment_method,CONSIGNACION', 'string', 'max:50'],
            'bank_amount' => ['nullable', 'required_if:payment_method,CONSIGNACION', 'integer', 'min:1'],
            'bank_deposit_type' => ['nullable', 'string', 'in:LOCAL,NACIONAL'],

            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'affiliate_id.exists' => 'El afiliado no existe en el sistema.',
            'days_eps.min' => 'Los días de EPS deben ser al menos 1.',
            'payment_method.in' => 'Medio de pago inválido. Opciones: EFECTIVO, CONSIGNACION, CREDITO, CUENTA_COBRO.',
        ];
    }
}
