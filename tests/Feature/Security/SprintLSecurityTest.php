<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Modules\Affiliates\Enums\AffiliateClientType;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Person;
use App\Modules\Affiliates\Models\PortalCredential;
use App\Modules\PILALiquidation\Models\PILAFileGeneration;
use App\Modules\Security\Models\AuditLog;
use App\Modules\Security\Models\GdprRequest;
use App\Modules\Security\Services\AuditLogService;
use App\Modules\Security\Services\CredentialAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SprintLSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function createAffiliate(): Affiliate
    {
        $person = Person::query()->create([
            'document_number' => 'P'.random_int(100000, 999999),
            'first_name' => 'Test',
            'first_surname' => 'User',
            'gender' => 'M',
            'address' => 'Calle Test 123',
            'cellphone' => '3001234567',
        ]);

        return Affiliate::query()->create([
            'person_id' => $person->id,
            'client_type' => AffiliateClientType::SERVICONLI,
        ]);
    }

    // ── RF-108: RBAC Spatie ──

    public function test_admin_role_exists_with_all_permissions(): void
    {
        $admin = Role::findByName('ADMIN', 'web');
        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasPermissionTo('affiliates.view'));
        $this->assertTrue($admin->hasPermissionTo('users.manage'));
        $this->assertTrue($admin->hasPermissionTo('gdpr.manage'));
    }

    public function test_five_roles_exist(): void
    {
        $roles = Role::whereIn('name', ['ADMIN', 'AFILIACIONES', 'PAGOS', 'CARTERA', 'CONSULTA'])->get();
        $this->assertCount(5, $roles);
    }

    public function test_consulta_role_has_only_view_permissions(): void
    {
        $consulta = Role::findByName('CONSULTA', 'web');
        $this->assertTrue($consulta->hasPermissionTo('affiliates.view'));
        $this->assertFalse($consulta->hasPermissionTo('affiliates.create'));
        $this->assertFalse($consulta->hasPermissionTo('affiliates.delete'));
        $this->assertFalse($consulta->hasPermissionTo('users.manage'));
    }

    public function test_admin_user_can_access_affiliates(): void
    {
        $response = $this->getJson('/api/affiliates');
        $response->assertStatus(200);
    }

    public function test_consulta_user_cannot_create_affiliate(): void
    {
        $user = User::factory()->create();
        $user->assignRole('CONSULTA');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/affiliates', []);
        $response->assertStatus(403);
    }

    public function test_afiliaciones_role_can_view_but_not_manage_liquidation(): void
    {
        $afl = Role::findByName('AFILIACIONES', 'web');
        $this->assertFalse($afl->hasPermissionTo('liquidation.create'));
        $this->assertFalse($afl->hasPermissionTo('liquidation.confirm'));
    }

    public function test_pagos_role_has_liquidation_permissions(): void
    {
        $pagos = Role::findByName('PAGOS', 'web');
        $this->assertTrue($pagos->hasPermissionTo('liquidation.create'));
        $this->assertTrue($pagos->hasPermissionTo('liquidation.confirm'));
        $this->assertTrue($pagos->hasPermissionTo('pila_files.generate'));
    }

    // ── RF-109: Audit Logs ──

    public function test_audit_log_created_on_affiliate_create(): void
    {
        $affiliate = $this->createAffiliate();

        $log = AuditLog::query()
            ->where('auditable_type', Affiliate::class)
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($log);
        $this->assertNotNull($log->new_values);
    }

    public function test_audit_log_service_records_update(): void
    {
        $affiliate = $this->createAffiliate();

        $affiliate->client_type = AffiliateClientType::INDEPENDIENTE;
        AuditLogService::logUpdated($affiliate);

        $log = AuditLog::query()
            ->where('action', 'updated')
            ->where('auditable_id', $affiliate->id)
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals(AffiliateClientType::SERVICONLI->value, $log->old_values['client_type']);
        $this->assertEquals(AffiliateClientType::INDEPENDIENTE->value, $log->new_values['client_type']);
    }

    public function test_audit_log_api_returns_paginated_results(): void
    {
        AuditLog::query()->create([
            'action' => 'created',
            'auditable_type' => 'TestModel',
            'auditable_id' => 1,
        ]);

        $response = $this->getJson('/api/audit-logs');
        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'current_page']);
    }

    // ── RF-110: Habeas Data ──

    public function test_gdpr_request_can_be_created(): void
    {
        $affiliate = $this->createAffiliate();

        $response = $this->postJson('/api/gdpr-requests', [
            'affiliate_id' => $affiliate->id,
            'type' => 'CONSULTA',
            'description' => 'Solicitud de datos personales',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('gdpr_requests', [
            'affiliate_id' => $affiliate->id,
            'type' => 'CONSULTA',
            'status' => 'PENDIENTE',
        ]);
    }

    public function test_gdpr_request_can_be_resolved(): void
    {
        $affiliate = $this->createAffiliate();

        $gdpr = GdprRequest::query()->create([
            'affiliate_id' => $affiliate->id,
            'type' => 'RECTIFICACION',
            'status' => 'PENDIENTE',
        ]);

        $response = $this->patchJson("/api/gdpr-requests/{$gdpr->id}/resolve", [
            'status' => 'RESUELTA',
            'resolution_notes' => 'Datos corregidos según solicitud.',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('gdpr_requests', [
            'id' => $gdpr->id,
            'status' => 'RESUELTA',
        ]);
    }

    public function test_gdpr_request_invalid_type_rejected(): void
    {
        $affiliate = $this->createAffiliate();

        $response = $this->postJson('/api/gdpr-requests', [
            'affiliate_id' => $affiliate->id,
            'type' => 'INVALID_TYPE',
        ]);

        $response->assertStatus(422);
    }

    public function test_gdpr_summary_endpoint(): void
    {
        $affiliate = $this->createAffiliate();

        GdprRequest::query()->create([
            'affiliate_id' => $affiliate->id,
            'type' => 'CONSULTA',
            'status' => 'PENDIENTE',
        ]);
        GdprRequest::query()->create([
            'affiliate_id' => $affiliate->id,
            'type' => 'SUPRESION',
            'status' => 'RESUELTA',
        ]);

        $response = $this->getJson('/api/gdpr-requests/summary');
        $response->assertStatus(200);
        $response->assertJsonFragment(['pending' => 1, 'resolved' => 1]);
    }

    // ── RF-111: Credential access log ──

    public function test_credential_access_is_logged(): void
    {
        $affiliate = $this->createAffiliate();

        $credential = PortalCredential::query()->create([
            'affiliate_id' => $affiliate->id,
            'portal_type' => 'OPERATOR_PILA',
            'username' => 'user@test.com',
            'password' => 'secret123',
        ]);

        CredentialAccessService::logAccess($credential);

        $this->assertDatabaseHas('sec_credential_access_logs', [
            'credential_id' => $credential->id,
            'action' => 'DECRYPT',
        ]);
    }

    // ── RF-112: Soft delete with reason ──

    public function test_affiliate_soft_delete_with_reason(): void
    {
        $affiliate = $this->createAffiliate();

        $affiliate->softDeleteWithReason('Retiro voluntario por solicitud del titular');

        $this->assertSoftDeleted('afl_affiliates', ['id' => $affiliate->id]);

        $deleted = Affiliate::withTrashed()->find($affiliate->id);
        $this->assertEquals('Retiro voluntario por solicitud del titular', $deleted->deleted_reason);
        $this->assertNotNull($deleted->deleted_by);
    }

    // ── RF-113: files:purge ──

    private function createOldPilaFile(string $path, int $monthsAgo = 30): PILAFileGeneration
    {
        $file = PILAFileGeneration::query()->create([
            'period_year' => 2023,
            'period_month' => 1,
            'planilla_type' => 'E',
            'file_path' => $path,
            'file_format' => 'ARUS',
            'affiliates_count' => 1,
            'status' => 'GENERADO',
        ]);

        $file->forceFill(['created_at' => now()->subMonths($monthsAgo)])->saveQuietly();

        return $file->fresh();
    }

    public function test_files_purge_dry_run(): void
    {
        $this->createOldPilaFile('pila/test_old.txt');

        $this->artisan('files:purge', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('pila_file_generations', [
            'file_path' => 'pila/test_old.txt',
            'status' => 'GENERADO',
        ]);
    }

    public function test_files_purge_marks_old_files_as_purged(): void
    {
        Storage::fake();
        Storage::put('pila/old_file.txt', 'content');

        $file = $this->createOldPilaFile('pila/old_file.txt');

        $this->artisan('files:purge')
            ->assertSuccessful();

        $this->assertDatabaseHas('pila_file_generations', [
            'id' => $file->id,
            'status' => 'PURGED',
        ]);
    }

    public function test_files_purge_ignores_recent_files(): void
    {
        $file = PILAFileGeneration::query()->create([
            'period_year' => 2026,
            'period_month' => 3,
            'planilla_type' => 'E',
            'file_path' => 'pila/recent.txt',
            'file_format' => 'ARUS',
            'affiliates_count' => 1,
            'status' => 'GENERADO',
        ]);

        $this->artisan('files:purge')
            ->assertSuccessful();

        $this->assertDatabaseHas('pila_file_generations', [
            'id' => $file->id,
            'status' => 'GENERADO',
        ]);
    }
}
