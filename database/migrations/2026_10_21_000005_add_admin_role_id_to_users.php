<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $legacyRoles = ['admin', 'viewer', 'pharmacist'];

        // Ensure the three existing account types have matching role records.
        foreach ($legacyRoles as $name) {
            if (!DB::table('admin_roles')->where('name', $name)->exists()) {
                DB::table('admin_roles')->insert([
                    'name' => $name,
                    'description' => ucfirst($name).' role (migrated from users.role)',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $roleIds = DB::table('admin_roles')->pluck('id', 'name');
        $values = DB::table('users')->distinct()->pluck('role');
        $unknown = $values->filter(function ($value) use ($roleIds) {
            return $value !== null
                && !isset($roleIds[(string) $value])
                && !(ctype_digit((string) $value) && $roleIds->contains((int) $value));
        });
        if ($unknown->isNotEmpty()) {
            throw new RuntimeException('Unmapped users.role values: '.$unknown->implode(', '));
        }

        // Use a temporary numeric column: avoids unsafe VARCHAR -> BIGINT casts
        // on PostgreSQL and preserves assignments during the conversion.
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_migration_id')->nullable();
        });

        foreach ($roleIds as $name => $id) {
            DB::table('users')->where('role', $name)->update(['role_migration_id' => $id]);
        }
        foreach ($roleIds as $id) {
            DB::table('users')->where('role', (string) $id)->update(['role_migration_id' => $id]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('role_migration_id', 'role');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('role', 'users_role_admin_roles_fk')
                ->references('id')->on('admin_roles')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('users_role_admin_roles_fk');
            $table->string('role_migration_text')->nullable();
        });
        foreach (DB::table('admin_roles')->pluck('name', 'id') as $id => $name) {
            DB::table('users')->where('role', $id)->update(['role_migration_text' => $name]);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('role_migration_text', 'role');
        });
        // Deliberately retain admin_roles records: they may be referenced elsewhere.
    }
};