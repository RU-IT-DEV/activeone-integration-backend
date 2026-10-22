<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('admin_navigations', function (Blueprint $table) {
            $table->json('available_actions')->nullable()->after('icon'); 
        });
        
        $defaults = [
            '/activeone/dashboard' => ['can_view'],
            '/activeone/orders' => ['can_view', 'can_view_history', 'can_rerun_ic_transaction', 'can_validate'],
            '/activeone/products' => ['can_view', 'can_create', 'can_update', 'can_delete'],
            '/activeone/navigations' => ['can_view', 'can_create', 'can_update', 'can_delete'],
            '/activeone/roles' => ['can_view', 'can_create', 'can_update', 'can_delete'],
        ];
        
        foreach ($defaults as $route => $actions)
            DB::table('admin_navigations')->where('route', $route)->update(['available_actions' => json_encode($actions)]);
    }
    
    public function down(): void
    {
        Schema::table('admin_navigations', function (Blueprint $table) {
            $table->dropColumn('available_actions'); });
    }
};