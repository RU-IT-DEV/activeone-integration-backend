<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Navigations;

class NavigationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $navigations = [
            [
                'icon' => 'mdi-view-dashboard-outline',
                'name' => 'Dashboard',
                'href' => '/admin/admin/dashboard',
                'actions' => '["view"]'
            ],
            [
                'icon' => 'mdi-account',
                'name' => 'Users',
                'href' => '/admin/users',
                'actions' => '["view"]'
            ],
            [
                'icon' => 'mdi-domain',
                'name' => 'Companies',
                'href' => '/admin/companies',
                'actions' => '["create","edit","delete","view","edit.details","edit.account_poc","edit.attachments","edit.contract","view.details","view.contracts","view.account_poc","view.attachments","refresh","create.attachments","delete.attachments"]'
            ],
            [
                'icon' => 'mdi-account-heart',
                'name' => 'Benefits',
                'href' => '/admin/benefits',
                'actions' => '["view"]'
            ],
            [
                'icon' => 'mdi-account-group',
                'name' => 'Members',
                'href' => '/admin/members',
                'actions' => '["view"]'
            ],
            [
                'icon' => 'mdi-navigation-variant-outline',
                'name' => 'Navigations',
                'href' => '/admin/navigations',
                'actions' => '["create","edit","delete","view","refresh","edit.actions","edit.href","view.actions"]'
            ],
            [
                'icon' => 'mdi-key-variant',
                'name' => 'Roles',
                'href' => '/admin/roles',
                'actions' => '["create","edit","delete","view","refresh","edit.role_access","view.role_access"]'
            ],
            [
                'icon' => 'mdi-chart-pie',
                'name' => 'Reports',
                'href' => '/admin/reports',
                'actions' => '["view"]'
            ],
            [
                'icon' => 'mdi-cogs',
                'name' => 'Settings',
                'href' => '/admin/settings',
                'actions' => '["view"]'
            ],
            [
                'icon' => 'mdi-format-list-text',
                'name' => 'Logs',
                'href' => '/admin/logs',
                'actions' => '["view", "complexfiltering", "refresh", "view.logs", "view.log"]'
            ],
            [
                'icon' => 'mdi-gavel',
                'name' => 'Adjudication',
                'href' => '/admin/adjudication',
                'actions' => '["filter", "adjudicate", "refresh"]'
            ],
            [
                'icon' => '',
                'name' => 'Claims Filing',
                'href' => '/flexible-benefits',
                'actions' => '["create", "view", "refresh"]'
            ],
            [
                'icon' => 'mdi-file-plus',
                'name' => 'Filing',
                'href' => '/admin/filing',
                'actions' => '["create"]'
            ],
        ];

        foreach ($navigations as $navigation) {
            Navigations::create($navigation);
        }
    }
}
