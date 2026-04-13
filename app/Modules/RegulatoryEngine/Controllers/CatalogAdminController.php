<?php

namespace App\Modules\RegulatoryEngine\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * RF-116 — API admin CRUD genérica para catálogos normativos (cfg_*).
 *
 * Whitelist de tablas permitidas. Solo ADMIN puede acceder.
 *
 * GET    /api/admin/catalogs                     → lista de catálogos disponibles
 * GET    /api/admin/catalogs/{table}              → listar registros
 * GET    /api/admin/catalogs/{table}/{id}         → detalle de un registro
 * POST   /api/admin/catalogs/{table}              → crear registro
 * PUT    /api/admin/catalogs/{table}/{id}         → actualizar registro
 * DELETE /api/admin/catalogs/{table}/{id}         → eliminar registro
 *
 * @see DOCUMENTO_RECTOR §14.6, RF-116
 */
final class CatalogAdminController extends Controller
{
    /** Catálogos permitidos para CRUD admin. */
    private const ALLOWED_TABLES = [
        'cfg_regulatory_parameters',
        'cfg_contributor_types',
        'cfg_contributor_type_subsystems',
        'cfg_planilla_types',
        'cfg_novelty_types',
        'cfg_novelty_rules',
        'cfg_ss_entities',
        'cfg_pila_operator_branches',
        'cfg_payment_calendar_rules',
        'cfg_payment_deadline_overrides',
        'cfg_colombian_holidays',
        'cfg_validation_rules',
        'cfg_ciiu_codes',
        'cfg_service_types',
        'cfg_cancellation_reasons',
        'cfg_retirement_reasons',
        'cfg_disability_types',
        'cfg_disability_subtypes',
        'cfg_diagnosis_cie10',
        'cfg_payment_methods',
        'cfg_receipt_concepts',
        'cfg_affiliate_statuses',
        'cfg_consecutive_formats',
        'cfg_solidarity_fund_scale',
        'cfg_pila_occupation_codes',
        'cfg_pila_file_format_fields',
        'cfg_operational_exceptions',
    ];

    public function catalogs(): JsonResponse
    {
        $list = collect(self::ALLOWED_TABLES)->map(fn (string $table) => [
            'table' => $table,
            'count' => DB::table($table)->count(),
        ]);

        return response()->json($list);
    }

    public function index(Request $request, string $table): JsonResponse
    {
        $this->assertAllowed($table);

        $query = DB::table($table);

        if ($request->filled('search') && Schema::hasColumn($table, 'name')) {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }

        $perPage = min((int) ($request->input('per_page', 50)), 200);

        return response()->json($query->orderBy('id')->paginate($perPage));
    }

    public function show(string $table, int $id): JsonResponse
    {
        $this->assertAllowed($table);

        $record = DB::table($table)->find($id);
        if ($record === null) {
            return response()->json(['message' => 'Registro no encontrado.'], 404);
        }

        return response()->json($record);
    }

    public function store(Request $request, string $table): JsonResponse
    {
        $this->assertAllowed($table);

        $columns = Schema::getColumnListing($table);
        $data = $request->only($columns);
        unset($data['id']);
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table($table)->insertGetId($data);

        return response()->json(DB::table($table)->find($id), 201);
    }

    public function update(Request $request, string $table, int $id): JsonResponse
    {
        $this->assertAllowed($table);

        $record = DB::table($table)->find($id);
        if ($record === null) {
            return response()->json(['message' => 'Registro no encontrado.'], 404);
        }

        $columns = Schema::getColumnListing($table);
        $data = $request->only($columns);
        unset($data['id']);
        $data['updated_at'] = now();

        DB::table($table)->where('id', $id)->update($data);

        return response()->json(DB::table($table)->find($id));
    }

    public function destroy(string $table, int $id): JsonResponse
    {
        $this->assertAllowed($table);

        $deleted = DB::table($table)->where('id', $id)->delete();

        if ($deleted === 0) {
            return response()->json(['message' => 'Registro no encontrado.'], 404);
        }

        return response()->json(['message' => 'Registro eliminado.']);
    }

    private function assertAllowed(string $table): void
    {
        if (! in_array($table, self::ALLOWED_TABLES, true)) {
            throw new InvalidArgumentException("Catálogo '{$table}' no está permitido para edición admin.");
        }
    }
}
