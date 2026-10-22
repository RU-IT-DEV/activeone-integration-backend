<?php

namespace Database\Seeders;

use App\Models\AdminRoleNavigationPermission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $array = [
            [
                "admin_role_id" => 2,
                "admin_navigation_id" => 1,
                "can_view" => 1,
                "can_create" => 0,
                "can_update" => 0,
                "can_delete" => 0,
                "actions" => ["can_view"]
            ],
            [
                "admin_role_id" => 2,
                "admin_navigation_id" => 2,
                "can_view" => 1,
                "can_create" => 0,
                "can_update" => 0,
                "can_delete" => 0,
                "actions" => ["can_view", "can_view_history", "can_validate"]
            ],
            [
                "admin_role_id" => 2,
                "admin_navigation_id" => 4,
                "can_view" => 1,
                "can_create" => 0,
                "can_update" => 0,
                "can_delete" => 0,
                "actions" => ["can_view"]
            ],
            [
                "admin_role_id" => 3,
                "admin_navigation_id" => 1,
                "can_view" => 1,
                "can_create" => 0,
                "can_update" => 0,
                "can_delete" => 0,
                "actions" => ["can_view"]
            ],
            [
                "admin_role_id" => 3,
                "admin_navigation_id" => 2,
                "can_view" => 1,
                "can_create" => 0,
                "can_update" => 0,
                "can_delete" => 0,
                "actions" => ["can_view", "can_view_history"]
            ],
            [
                "admin_role_id" => 4,
                "admin_navigation_id" => 1,
                "can_view" => 1,
                "can_create" => 0,
                "can_update" => 0,
                "can_delete" => 0,
                "actions" => ["can_view"]
            ],
            [
                "admin_role_id" => 4,
                "admin_navigation_id" => 2,
                "can_view" => 1,
                "can_create" => 0,
                "can_update" => 0,
                "can_delete" => 0,
                "actions" => ["can_view", "can_view_history"]
            ],
            [
                "admin_role_id" => 4,
                "admin_navigation_id" => 4,
                "can_view" => 1,
                "can_create" => 0,
                "can_update" => 0,
                "can_delete" => 0,
                "actions" => ["can_view"]
            ],
            [
                "admin_role_id" => 1,
                "admin_navigation_id" => 1,
                "can_view" => 1,
                "can_create" => 0,
                "can_update" => 0,
                "can_delete" => 0,
                "actions" => ["can_view"]
            ],
            [
                "admin_role_id" => 1,
                "admin_navigation_id" => 2,
                "can_view" => 1,
                "can_create" => 0,
                "can_update" => 0,
                "can_delete" => 0,
                "actions" => ["can_view", "can_validate", "can_rerun_ic_transaction", "can_view_history"]
            ],
            [
                "admin_role_id" => 1,
                "admin_navigation_id" => 3,
                "can_view" => 1,
                "can_create" => 1,
                "can_update" => 1,
                "can_delete" => 1,
                "actions" => ["can_view", "can_create", "can_update", "can_delete"]
            ],
            [
                "admin_role_id" => 1,
                "admin_navigation_id" => 4,
                "can_view" => 1,
                "can_create" => 0,
                "can_update" => 0,
                "can_delete" => 0,
                "actions" => ["can_view"]
            ],
            [
                "admin_role_id" => 1,
                "admin_navigation_id" => 6,
                "can_view" => 1,
                "can_create" => 1,
                "can_update" => 1,
                "can_delete" => 1,
                "actions" => ["can_view", "can_create", "can_update", "can_delete"]
            ],
            [
                "admin_role_id" => 1,
                "admin_navigation_id" => 7,
                "can_view" => 1,
                "can_create" => 1,
                "can_update" => 1,
                "can_delete" => 1,
                "actions" => ["can_view", "can_create", "can_update", "can_delete"]
            ],
            [
                "admin_role_id" => 5,
                "admin_navigation_id" => 1,
                "can_view" => 1,
                "can_create" => 0,
                "can_update" => 0,
                "can_delete" => 0,
                "actions" => ["can_view"]
            ],
            [
                "admin_role_id" => 5,
                "admin_navigation_id" => 2,
                "can_view" => 1,
                "can_create" => 0,
                "can_update" => 0,
                "can_delete" => 0,
                "actions" => ["can_view", "can_view_history", "can_validate"]
            ],
            [
                "admin_role_id" => 5,
                "admin_navigation_id" => 3,
                "can_view" => 1,
                "can_create" => 1,
                "can_update" => 1,
                "can_delete" => 0,
                "actions" => ["can_view", "can_create", "can_update"]
            ],
            [
                "admin_role_id" => 5,
                "admin_navigation_id" => 4,
                "can_view" => 1,
                "can_create" => 0,
                "can_update" => 0,
                "can_delete" => 0,
                "actions" => ["can_view"]
            ],
            [
                "admin_role_id" => 5,
                "admin_navigation_id" => 5,
                "can_view" => 1,
                "can_create" => 0,
                "can_update" => 0,
                "can_delete" => 0,
                "actions" => ["can_view"]
            ],
            [
                "admin_role_id" => 5,
                "admin_navigation_id" => 6,
                "can_view" => 1,
                "can_create" => 0,
                "can_update" => 0,
                "can_delete" => 0,
                "actions" => ["can_view"]
            ],
            [
                "admin_role_id" => 5,
                "admin_navigation_id" => 7,
                "can_view" => 1,
                "can_create" => 0,
                "can_update" => 0,
                "can_delete" => 0,
                "actions" => ["can_view"]
            ]
        ];

        foreach ($array as $value) {
            AdminRoleNavigationPermission::create($value);
        }
    }
}
