<?php

// RF-108 — RBAC 5 roles con permisos granulares via Spatie Permission

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Affiliates module
            'affiliates.view', 'affiliates.create', 'affiliates.update', 'affiliates.delete', 'affiliates.export',
            // Enrollment / Reentry
            'enrollment.create', 'enrollment.update',
            'reentry.create', 'reentry.update',
            // Novelties
            'novelties.create', 'novelties.update',
            // Beneficiaries
            'beneficiaries.view', 'beneficiaries.create', 'beneficiaries.update', 'beneficiaries.delete',
            // Employers
            'employers.view', 'employers.create', 'employers.update', 'employers.delete',
            // PILA Liquidation
            'liquidation.view', 'liquidation.create', 'liquidation.confirm', 'liquidation.cancel',
            // Batch Liquidation
            'batch.view', 'batch.create', 'batch.confirm',
            // PILA Files
            'pila_files.generate', 'pila_files.download',
            // Billing — Cuentas Cobro
            'cuentas_cobro.view', 'cuentas_cobro.create', 'cuentas_cobro.pay',
            // Billing — Recibos / Invoices
            'invoices.view', 'invoices.create', 'invoices.cancel',
            // Cash Reconciliation
            'cash.view', 'cash.reconcile', 'cash.close',
            // Advisors
            'advisors.view', 'advisors.create', 'advisors.update', 'advisors.delete',
            'commissions.view', 'commissions.update',
            // ThirdParties
            'deposits.view', 'deposits.create',
            'receivables.view', 'receivables.update',
            // Disabilities
            'disabilities.view', 'disabilities.create', 'disabilities.update', 'disabilities.delete',
            // Communications
            'notifications.view', 'notifications.update',
            // Documents
            'documents.view', 'documents.download',
            // Dashboard & Reports
            'dashboard.view', 'reports.view',
            // Security & Admin
            'users.manage', 'roles.manage', 'audit.view',
            'config.view', 'config.update',
            // GDPR
            'gdpr.view', 'gdpr.manage',
            // Portal Credentials
            'credentials.view', 'credentials.decrypt',
        ];

        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        // RF-108: ADMIN — acceso completo
        $admin = Role::findOrCreate('ADMIN', 'web');
        $admin->syncPermissions($permissions);

        // RF-108: AFILIACIONES — gestión afiliados y novedades (Marcela)
        $afiliaciones = Role::findOrCreate('AFILIACIONES', 'web');
        $afiliaciones->syncPermissions([
            'affiliates.view', 'affiliates.create', 'affiliates.update', 'affiliates.export',
            'enrollment.create', 'enrollment.update',
            'reentry.create', 'reentry.update',
            'novelties.create', 'novelties.update',
            'beneficiaries.view', 'beneficiaries.create', 'beneficiaries.update', 'beneficiaries.delete',
            'employers.view',
            'disabilities.view', 'disabilities.create', 'disabilities.update',
            'documents.view', 'documents.download',
            'notifications.view', 'notifications.update',
            'credentials.view',
            'dashboard.view',
        ]);

        // RF-108: PAGOS — liquidación, planillas, cuentas de cobro (Katherine)
        $pagos = Role::findOrCreate('PAGOS', 'web');
        $pagos->syncPermissions([
            'affiliates.view', 'affiliates.export',
            'employers.view',
            'liquidation.view', 'liquidation.create', 'liquidation.confirm', 'liquidation.cancel',
            'batch.view', 'batch.create', 'batch.confirm',
            'pila_files.generate', 'pila_files.download',
            'cuentas_cobro.view', 'cuentas_cobro.create', 'cuentas_cobro.pay',
            'invoices.view', 'invoices.create',
            'cash.view', 'cash.reconcile', 'cash.close',
            'documents.view', 'documents.download',
            'notifications.view', 'notifications.update',
            'dashboard.view', 'reports.view',
        ]);

        // RF-108: CARTERA — cobro, mora, recibos (Natalia)
        $cartera = Role::findOrCreate('CARTERA', 'web');
        $cartera->syncPermissions([
            'affiliates.view', 'affiliates.export',
            'employers.view',
            'invoices.view', 'invoices.create', 'invoices.cancel',
            'cuentas_cobro.view', 'cuentas_cobro.pay',
            'cash.view',
            'advisors.view',
            'commissions.view', 'commissions.update',
            'deposits.view', 'deposits.create',
            'receivables.view', 'receivables.update',
            'documents.view', 'documents.download',
            'notifications.view', 'notifications.update',
            'dashboard.view', 'reports.view',
        ]);

        // RF-108: CONSULTA — solo lectura
        $consulta = Role::findOrCreate('CONSULTA', 'web');
        $consulta->syncPermissions([
            'affiliates.view',
            'employers.view',
            'liquidation.view',
            'batch.view',
            'cuentas_cobro.view',
            'invoices.view',
            'cash.view',
            'advisors.view',
            'commissions.view',
            'deposits.view',
            'receivables.view',
            'disabilities.view',
            'documents.view',
            'notifications.view',
            'dashboard.view', 'reports.view',
        ]);
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::whereIn('name', ['ADMIN', 'AFILIACIONES', 'PAGOS', 'CARTERA', 'CONSULTA'])->delete();
    }
};
