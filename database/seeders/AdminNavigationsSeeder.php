<?php

namespace Database\Seeders;

use App\Models\AdminNavigation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class AdminNavigationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AdminNavigation::insert([
            [
                'title' => "Dashboard",
                'route' => "/activeone/dashboard",
                'icon' => "mdi-view-dashboard-outline",
                'available_actions' => ['can_view'],
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'title' => "Orders",
                'route' => "/activeone/orders",
                'icon' => "mdi-clipboard-list-outline",
                'available_actions' => ["can_view", "can_view_history", "can_rerun_ic_transaction", "can_validate"],
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'title' => "Products",
                'route' => "/activeone/products",
                'icon' => "mdi-pill",
                'available_actions' => ["can_view", "can_create", "can_update", "can_delete"],
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'title' => "Customers",
                'route' => "/activeone/customers",
                'icon' => "mdi-account-group-outline",
                'available_actions' => ['can_view'],
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'title' => "Users",
                'route' => "/activeone/users",
                'icon' => "mdi-account-outline",
                'available_actions' => ['can_view'],
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'title' => "Roles",
                'route' => "/activeone/roles",
                'icon' => "mdi-shield-account-outline",
                'available_actions' => ["can_view", "can_create", "can_update", "can_delete"],
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'title' => "Navigations",
                'route' => "/activeone/navigations",
                'icon' => "mdi-menu",
                'available_actions' => ["can_view", "can_create", "can_update", "can_delete"],
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
        ]);
    }
}
