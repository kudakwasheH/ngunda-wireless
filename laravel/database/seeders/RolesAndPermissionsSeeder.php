<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /** permission => description */
    public const PERMISSIONS = [
        'dashboard.view' => 'View dashboard',
        'users.manage' => 'Manage staff users and roles',
        'packages.view' => 'View internet packages',
        'packages.manage' => 'Create/edit internet packages',
        'tokens.view' => 'View tokens and redemption history',
        'tokens.generate' => 'Generate tokens/vouchers',
        'tokens.revoke' => 'Revoke or suspend tokens',
        'tokens.print' => 'Print vouchers and QR codes',
        'customers.manage' => 'Manage customer records',
        'sales.create' => 'Sell tokens and record payments',
        'sales.view' => 'View sales and receipts',
        'reports.view' => 'View operational reports',
        'reports.financial' => 'View financial reports',
        'devices.view' => 'View connected devices',
        'devices.manage' => 'Disconnect, block and unblock devices',
        'bandwidth.manage' => 'Manage bandwidth limits',
        'network.view' => 'View network status',
        'network.configure' => 'Change network configuration',
        'settings.manage' => 'Manage business/portal settings',
        'audit.view' => 'View audit logs',
    ];

    public const ROLE_MAP = [
        Role::NETWORK_ADMIN => [
            'name' => 'Network Administrator',
            'description' => 'Network monitoring, device blocking, bandwidth control and technical configuration.',
            'permissions' => ['dashboard.view', 'packages.view', 'tokens.view', 'tokens.revoke', 'devices.view',
                'devices.manage', 'bandwidth.manage', 'network.view', 'network.configure', 'reports.view'],
        ],
        Role::SALES_OFFICER => [
            'name' => 'Sales Officer',
            'description' => 'Token generation, voucher sales, customer records and receipts.',
            'permissions' => ['dashboard.view', 'packages.view', 'tokens.view', 'tokens.generate', 'tokens.print',
                'customers.manage', 'sales.create', 'sales.view'],
        ],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name => $description) {
            Permission::updateOrCreate(['name' => $name], ['description' => $description]);
        }

        // Super admin holds everything implicitly (see User::hasPermission) but we also
        // attach all permissions so the role screen shows an accurate picture.
        $super = Role::updateOrCreate(['slug' => Role::SUPER_ADMIN], [
            'name' => 'Super Administrator',
            'description' => 'Full access to all functions, network configuration, financial records, users and reports.',
            'is_staff' => true,
        ]);
        $super->permissions()->sync(Permission::pluck('id'));

        foreach (self::ROLE_MAP as $slug => $def) {
            $role = Role::updateOrCreate(['slug' => $slug], [
                'name' => $def['name'], 'description' => $def['description'], 'is_staff' => true,
            ]);
            $role->permissions()->sync(Permission::whereIn('name', $def['permissions'])->pluck('id'));
        }

        // Portal-only identity: no admin login, no permissions.
        Role::updateOrCreate(['slug' => Role::CUSTOMER], [
            'name' => 'Customer',
            'description' => 'Captive portal authentication and session information only.',
            'is_staff' => false,
        ]);
    }
}
