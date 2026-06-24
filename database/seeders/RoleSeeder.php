<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Roles;
class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Roles::create([
            'name' => 'IT admin',
            'navigations' => json_encode([
                [
                    "id"=> 1,
                    "icon"=> "mdi-view-dashboard-outline",
                    "navigation_name"=> "Dashboard",
                    "actions"=> [
                      "view"
                    ]
                ],
                  [
                    "id"=> 2,
                    "icon"=> "mdi-account",
                    "navigation_name"=> "Users",
                    "actions"=> [
                      "view"
                    ]
                  ],
                  [
                    "id"=> 7,
                    "icon"=> "mdi-key-variant",
                    "navigation_name"=> "Roles",
                    "actions"=> [
                      "create",
                      "edit",
                      "delete",
                      "view",
                      "refresh",
                      "edit.role_access",
                      "view.role_access"
                    ]
                  ],
                  [
                    "id"=> 3,
                    "icon"=> "mdi-domain",
                    "navigation_name"=> "Companies",
                    "actions"=> [
                      "create",
                      "edit",
                      "delete",
                      "view",
                      "edit.details",
                      "edit.account_poc",
                      "edit.attachments",
                      "edit.contract",
                      "view.details",
                      "view.contracts",
                      "view.account_poc",
                      "view.attachments",
                      "refresh",
                      "create.attachments",
                      "delete.attachments"
                    ]
                  ],
                  [
                    "id"=> 4,
                    "icon"=> "mdi-account-heart",
                    "navigation_name"=> "Benefits",
                    "actions"=> [
                      "view"
                    ]
                  ],
                  [
                    "id"=> 10,
                    "icon"=> "mdi-format-list-text",
                    "navigation_name"=> "Logs",
                    "actions"=> [
                      "view"
                    ]
                  ],
                  [
                    "id"=> 6,
                    "icon"=> "mdi-navigation-variant-outline",
                    "navigation_name"=> "Navigations",
                    "actions"=> [
                      "create",
                      "edit",
                      "delete",
                      "view",
                      "refresh",
                      "edit.actions",
                      "edit.href",
                      "view.actions"
                    ]
                ]   
            ]),
        ]);
    }
}
