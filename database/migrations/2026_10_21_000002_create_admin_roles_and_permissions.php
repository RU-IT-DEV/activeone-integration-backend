<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('admin_roles', function(Blueprint $t) { $t->id(); $t->string('name',100); $t->string('slug',100)->unique(); $t->text('description')->nullable(); $t->timestamps(); });
  Schema::create('admin_role_permissions', function(Blueprint $t) {
   $t->id(); $t->foreignId('admin_role_id')->constrained('admin_roles')->cascadeOnDelete();
   $t->foreignId('admin_navigation_id')->constrained('admin_navigations')->cascadeOnDelete();
   $t->boolean('can_view')->default(false); $t->boolean('can_create')->default(false);
   $t->boolean('can_update')->default(false); $t->boolean('can_delete')->default(false);
   $t->unique(['admin_role_id','admin_navigation_id'], 'admin_role_navigation_unique');
  });
 }
 public function down(): void { Schema::dropIfExists('admin_role_permissions'); Schema::dropIfExists('admin_roles'); }
};
