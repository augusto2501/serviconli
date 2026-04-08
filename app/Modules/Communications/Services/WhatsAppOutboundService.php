<?php

namespace App\Modules\Communications\Services;

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Communications\Models\WhatsappLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * RF-106 — Twilio WhatsApp Business (fallback log-only si no hay credenciales).
 */
final class WhatsAppOutboundService
{
    private const TEMPLATES = [
        'welcome' => 'Bienvenido a Serviconli, :name.',
        'payment_reminder' => 'Recordatorio de pago PILA, :name.',
        'mora_beneficiary_alert' => 'Serviconli: su afiliación presenta mora superior a un mes. Regularice para proteger coberturas de beneficiarios (normativa vigente). Ref: :ref',
        'confirmation' => 'Confirmamos recepción, :name.',
    ];

    /**
     * @param  array<string, string>  $variables
     */
    public function sendTemplate(Affiliate $affiliate, string $templateCode, array $variables = []): WhatsappLog
    {
        $affiliate->loadMissing('person');

        $bodyTemplate = self::TEMPLATES[$templateCode] ?? self::TEMPLATES['confirmation'];
        $variables['ref'] = $variables['ref'] ?? (string) $affiliate->id;
        $variables['name'] = $variables['name'] ?? (string) ($affiliate->person?->first_name ?? 'afiliado');

        $body = $bodyTemplate;
        foreach ($variables as $key => $value) {
            $body = str_replace(':'.$key, $value, $body);
        }

        $rawPhone = $affiliate->person?->cellphone ?? $affiliate->person?->phone1;
        $e164 = $this->normalizeToE164($rawPhone);

        $payload = [
            'body' => $body,
            'templateCode' => $templateCode,
            'variables' => $variables,
        ];

        if ($e164 === null) {
            return WhatsappLog::query()->create([
                'affiliate_id' => $affiliate->id,
                'template_code' => $templateCode,
                'to_number' => null,
                'provider' => 'log',
                'status' => 'failed',
                'payload' => $payload,
                'error_message' => 'Sin número de celular/teléfono en la persona.',
                'triggered_by' => Auth::id(),
            ]);
        }

        $sid = config('services.twilio.account_sid');
        $token = config('services.twilio.auth_token');
        $from = config('services.twilio.whatsapp_from');

        if (! is_string($sid) || $sid === '' || ! is_string($token) || $token === '' || ! is_string($from) || $from === '') {
            return WhatsappLog::query()->create([
                'affiliate_id' => $affiliate->id,
                'template_code' => $templateCode,
                'to_number' => $e164,
                'provider' => 'log',
                'status' => 'sent',
                'payload' => $payload,
                'error_message' => null,
                'triggered_by' => Auth::id(),
            ]);
        }

        try {
            $url = sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json', $sid);
            $response = Http::asForm()
                ->withBasicAuth($sid, $token)
                ->post($url, [
                    'From' => str_starts_with($from, 'whatsapp:') ? $from : 'whatsapp:'.$from,
                    'To' => 'whatsapp:'.$e164,
                    'Body' => $body,
                ]);

            if (! $response->successful()) {
                return WhatsappLog::query()->create([
                    'affiliate_id' => $affiliate->id,
                    'template_code' => $templateCode,
                    'to_number' => $e164,
                    'provider' => 'twilio',
                    'status' => 'failed',
                    'payload' => $payload,
                    'error_message' => $response->body(),
                    'triggered_by' => Auth::id(),
                ]);
            }

            $sidMsg = $response->json('sid');

            return WhatsappLog::query()->create([
                'affiliate_id' => $affiliate->id,
                'template_code' => $templateCode,
                'to_number' => $e164,
                'provider' => 'twilio',
                'external_id' => is_string($sidMsg) ? $sidMsg : null,
                'status' => 'sent',
                'payload' => $payload,
                'error_message' => null,
                'triggered_by' => Auth::id(),
            ]);
        } catch (\Throwable $e) {
            return WhatsappLog::query()->create([
                'affiliate_id' => $affiliate->id,
                'template_code' => $templateCode,
                'to_number' => $e164,
                'provider' => 'twilio',
                'status' => 'failed',
                'payload' => $payload,
                'error_message' => $e->getMessage(),
                'triggered_by' => Auth::id(),
            ]);
        }
    }

    private function normalizeToE164(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === null || $digits === '') {
            return null;
        }

        if (Str::startsWith($digits, '57') && strlen($digits) >= 12) {
            return '+'.$digits;
        }

        if (strlen($digits) === 10) {
            return '+57'.$digits;
        }

        if (Str::startsWith($digits, '0')) {
            $digits = ltrim($digits, '0');
        }

        return strlen($digits) >= 10 ? '+'.$digits : null;
    }
}
