<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admin_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->timestamps(); 
        });
        
        Schema::create('admin_role_navigation_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_role_id')->constrained('admin_roles')->cascadeOnDelete();
            $table->foreignId('admin_navigation_id')->constrained('admin_navigations')->cascadeOnDelete();
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_update')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->unique(['admin_role_id', 'admin_navigation_id'], 'admin_role_navigation_unique');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('admin_role_navigation_permissions');
        Schema::dropIfExists('admin_roles');
    }
};