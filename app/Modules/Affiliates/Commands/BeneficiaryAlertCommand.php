<?php

namespace App\Modules\Affiliates\Commands;

use App\Modules\Affiliates\Models\Beneficiary;
use App\Modules\Communications\Models\CommNotification;
use App\Modules\Communications\Services\WhatsAppOutboundService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * RF-018: Alertas automáticas de beneficiarios.
 *
 * Detecta beneficiarios próximos a cumplir 18 años (30 días),
 * certificados de estudiante por vencer (30 días), y
 * certificados de discapacidad / protección por vencer (30 días).
 *
 * Genera notificación interna + WhatsApp opcional al afiliado titular.
 *
 * @see DOCUMENTO_RECTOR §1.4
 */
class BeneficiaryAlertCommand extends Command
{
    protected $signature = 'beneficiaries:alert
        {--days=30 : Días de anticipación para alertas}
        {--dry-run : Simular sin crear notificaciones}';

    protected $description = 'RF-018: Genera alertas para beneficiarios próximos a cumplir 18 años o con certificados por vencer';

    public function handle(WhatsAppOutboundService $whatsApp): int
    {
        $days = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');
        $now = Carbon::today();

        $alerts = [];

        $this->detectTurning18($now, $days, $alerts);
        $this->detectStudentCertExpiring($now, $days, $alerts);
        $this->detectProtectionEndDate($now, $days, $alerts);

        if (count($alerts) === 0) {
            $this->info('No se encontraron beneficiarios con alertas pendientes.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Se encontraron %d alerta(s).', count($alerts)));

        if ($dryRun) {
            foreach ($alerts as $alert) {
                $this->line(sprintf(
                    '  [DRY-RUN] %s — %s (afiliado #%d)',
                    $alert['type'],
                    $alert['beneficiary_name'],
                    $alert['affiliate_id'],
                ));
            }

            return self::SUCCESS;
        }

        $created = 0;
        foreach ($alerts as $alert) {
            CommNotification::query()->create([
                'user_id' => null,
                'type' => 'ALERTA_BENEFICIARIO',
                'title' => $alert['title'],
                'body' => $alert['body'],
                'action_url' => '/afiliados/'.$alert['affiliate_id'].'/ficha',
            ]);

            $affiliate = $alert['affiliate'];
            if ($affiliate !== null) {
                $whatsApp->sendTemplate($affiliate, 'payment_reminder', [
                    'name' => $alert['beneficiary_name'],
                    'message' => $alert['body'],
                ]);
            }

            $created++;
        }

        $this->info(sprintf('Se crearon %d notificación(es).', $created));

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  &$alerts
     */
    private function detectTurning18(Carbon $now, int $days, array &$alerts): void
    {
        $beneficiaries = Beneficiary::query()
            ->whereNotNull('birth_date')
            ->whereBetween('birth_date', [
                $now->copy()->subYears(18)->toDateString(),
                $now->copy()->subYears(18)->addDays($days)->toDateString(),
            ])
            ->with(['affiliate.person'])
            ->get();

        foreach ($beneficiaries as $b) {
            $turnsOn = $b->birth_date->copy()->addYears(18);
            $daysLeft = $now->diffInDays($turnsOn, false);

            $alerts[] = [
                'type' => 'CUMPLE_18',
                'affiliate_id' => $b->affiliate_id,
                'affiliate' => $b->affiliate,
                'beneficiary_name' => trim($b->first_name.' '.$b->surnames),
                'title' => 'Beneficiario próximo a cumplir 18 años',
                'body' => sprintf(
                    'El beneficiario %s del afiliado #%d cumple 18 años el %s (%d días restantes).',
                    trim($b->first_name.' '.$b->surnames),
                    $b->affiliate_id,
                    $turnsOn->format('d/m/Y'),
                    max(0, $daysLeft),
                ),
            ];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  &$alerts
     */
    private function detectStudentCertExpiring(Carbon $now, int $days, array &$alerts): void
    {
        $beneficiaries = Beneficiary::query()
            ->whereNotNull('student_cert_expires')
            ->whereBetween('student_cert_expires', [
                $now->toDateString(),
                $now->copy()->addDays($days)->toDateString(),
            ])
            ->with(['affiliate.person'])
            ->get();

        foreach ($beneficiaries as $b) {
            $daysLeft = $now->diffInDays($b->student_cert_expires, false);

            $alerts[] = [
                'type' => 'CERT_ESTUDIANTE',
                'affiliate_id' => $b->affiliate_id,
                'affiliate' => $b->affiliate,
                'beneficiary_name' => trim($b->first_name.' '.$b->surnames),
                'title' => 'Certificado de estudiante próximo a vencer',
                'body' => sprintf(
                    'El certificado de estudiante de %s (afiliado #%d) vence el %s (%d días restantes).',
                    trim($b->first_name.' '.$b->surnames),
                    $b->affiliate_id,
                    $b->student_cert_expires->format('d/m/Y'),
                    max(0, $daysLeft),
                ),
            ];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  &$alerts
     */
    private function detectProtectionEndDate(Carbon $now, int $days, array &$alerts): void
    {
        $beneficiaries = Beneficiary::query()
            ->whereNotNull('protection_end_date')
            ->whereBetween('protection_end_date', [
                $now->toDateString(),
                $now->copy()->addDays($days)->toDateString(),
            ])
            ->with(['affiliate.person'])
            ->get();

        foreach ($beneficiaries as $b) {
            $daysLeft = $now->diffInDays($b->protection_end_date, false);

            $alerts[] = [
                'type' => 'FIN_PROTECCION',
                'affiliate_id' => $b->affiliate_id,
                'affiliate' => $b->affiliate,
                'beneficiary_name' => trim($b->first_name.' '.$b->surnames),
                'title' => 'Fecha de protección próxima a vencer',
                'body' => sprintf(
                    'La protección de %s (afiliado #%d) finaliza el %s (%d días restantes).',
                    trim($b->first_name.' '.$b->surnames),
                    $b->affiliate_id,
                    $b->protection_end_date->format('d/m/Y'),
                    max(0, $daysLeft),
                ),
            ];
        }
    }
}
