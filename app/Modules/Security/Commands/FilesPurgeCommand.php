<?php

namespace App\Modules\Security\Commands;

use App\Modules\PILALiquidation\Models\PILAFileGeneration;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * RF-113 — purga de archivos PILA con más de 2 años.
 *
 * Los registros en BD se mantienen indefinidamente;
 * solo los archivos físicos en disco se eliminan.
 *
 * @see DOCUMENTO_RECTOR §14.5
 */
class FilesPurgeCommand extends Command
{
    protected $signature = 'files:purge
        {--months=24 : Antigüedad mínima en meses para purga}
        {--dry-run : Simular sin eliminar archivos}';

    protected $description = 'RF-113: Purgar archivos PILA generados con más de 2 años de antigüedad';

    public function handle(): int
    {
        $months = (int) $this->option('months');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = Carbon::now()->subMonths($months);

        $files = PILAFileGeneration::query()
            ->whereNotNull('file_path')
            ->where('created_at', '<', $cutoff)
            ->where('status', '!=', 'PURGED')
            ->get();

        if ($files->isEmpty()) {
            $this->info('No hay archivos elegibles para purga.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s archivos anteriores a %s encontrados.',
            $files->count(),
            $cutoff->format('Y-m-d'),
        ));

        $purged = 0;
        $errors = 0;

        foreach ($files as $file) {
            if ($dryRun) {
                $this->line("[DRY-RUN] Purgaría: {$file->file_path} (ID: {$file->id})");
                $purged++;

                continue;
            }

            $deleted = Storage::delete($file->file_path);

            if ($deleted || ! Storage::exists($file->file_path)) {
                $file->update(['status' => 'PURGED']);
                $purged++;
                $this->line("Purgado: {$file->file_path} (ID: {$file->id})");
            } else {
                $errors++;
                $this->error("Error purgando: {$file->file_path} (ID: {$file->id})");
            }
        }

        $this->info(sprintf(
            '%sPurgados: %d | Errores: %d',
            $dryRun ? '[DRY-RUN] ' : '',
            $purged,
            $errors,
        ));

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
