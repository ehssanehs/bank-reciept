<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /** @var array<string,array<int,string>> role slug => permission slugs */
    private const MATRIX = [
        Role::SUPER_ADMIN => '*',
        Role::ADMIN => [
            Permission::VIEW_PAYMENTS, Permission::VIEW_RECEIPTS, Permission::VIEW_BANK_SMS,
            Permission::APPROVE_PAYMENTS, Permission::REJECT_PAYMENTS, Permission::MANAGE_MATCHING_RULES,
            Permission::MANAGE_BANKS, Permission::MANAGE_BANK_PARSERS, Permission::MANAGE_TELEGRAM,
            Permission::MANAGE_USERS, Permission::MANAGE_SETTINGS, Permission::MANAGE_DEVICES,
            Permission::VIEW_AUDIT, Permission::VIEW_REPORTS,
        ],
        Role::PAYMENT_REVIEWER => [
            Permission::VIEW_PAYMENTS, Permission::VIEW_RECEIPTS, Permission::VIEW_BANK_SMS,
            Permission::APPROVE_PAYMENTS, Permission::REJECT_PAYMENTS, Permission::VIEW_AUDIT,
        ],
        Role::SUPPORT => [
            Permission::VIEW_PAYMENTS, Permission::VIEW_RECEIPTS, Permission::VIEW_BANK_SMS,
            Permission::VIEW_AUDIT, Permission::VIEW_REPORTS,
        ],
        Role::READ_ONLY => [
            Permission::VIEW_PAYMENTS, Permission::VIEW_REPORTS, Permission::VIEW_AUDIT,
        ],
    ];

    public function run(): void
    {
        $permissions = [
            ['name' => 'View Payments', 'slug' => Permission::VIEW_PAYMENTS, 'group' => 'payments'],
            ['name' => 'View Receipts', 'slug' => Permission::VIEW_RECEIPTS, 'group' => 'payments'],
            ['name' => 'View Bank SMS', 'slug' => Permission::VIEW_BANK_SMS, 'group' => 'sms'],
            ['name' => 'Approve Payments', 'slug' => Permission::APPROVE_PAYMENTS, 'group' => 'payments'],
            ['name' => 'Reject Payments', 'slug' => Permission::REJECT_PAYMENTS, 'group' => 'payments'],
            ['name' => 'Manage Matching Rules', 'slug' => Permission::MANAGE_MATCHING_RULES, 'group' => 'matching'],
            ['name' => 'Manage Banks', 'slug' => Permission::MANAGE_BANKS, 'group' => 'banks'],
            ['name' => 'Manage Bank Parsers', 'slug' => Permission::MANAGE_BANK_PARSERS, 'group' => 'banks'],
            ['name' => 'Manage Telegram', 'slug' => Permission::MANAGE_TELEGRAM, 'group' => 'telegram'],
            ['name' => 'Manage Users', 'slug' => Permission::MANAGE_USERS, 'group' => 'admin'],
            ['name' => 'Manage Settings', 'slug' => Permission::MANAGE_SETTINGS, 'group' => 'admin'],
            ['name' => 'View Audit', 'slug' => Permission::VIEW_AUDIT, 'group' => 'audit'],
            ['name' => 'View Reports', 'slug' => Permission::VIEW_REPORTS, 'group' => 'reports'],
            ['name' => 'Manage Devices', 'slug' => Permission::MANAGE_DEVICES, 'group' => 'devices'],
        ];

        foreach ($permissions as $p) {
            Permission::query()->updateOrCreate(['slug' => $p['slug']], $p);
        }

        $roles = [
            ['name' => 'Super Admin', 'slug' => Role::SUPER_ADMIN, 'description' => 'Full access to every feature.'],
            ['name' => 'Admin', 'slug' => Role::ADMIN, 'description' => 'Day-to-day administration.'],
            ['name' => 'Payment Reviewer', 'slug' => Role::PAYMENT_REVIEWER, 'description' => 'Review and decide on payments.'],
            ['name' => 'Support', 'slug' => Role::SUPPORT, 'description' => 'View payments and assist customers.'],
            ['name' => 'Read Only', 'slug' => Role::READ_ONLY, 'description' => 'View-only access.'],
        ];

        foreach ($roles as $r) {
            Role::query()->updateOrCreate(['slug' => $r['slug']], $r);
        }

        $all = Permission::pluck('id', 'slug');

        foreach (self::MATRIX as $roleSlug => $perms) {
            $role = Role::query()->where('slug', $roleSlug)->firstOrFail();
            if ($perms === '*') {
                $role->permissions()->sync($all->values());
            } else {
                $ids = collect($perms)->map(fn ($slug) => $all[$slug])->values();
                $role->permissions()->sync($ids);
            }
        }
    }
}
