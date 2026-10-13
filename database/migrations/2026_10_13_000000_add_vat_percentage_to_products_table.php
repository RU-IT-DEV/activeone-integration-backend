<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'vat_percentage')) {
                $table->decimal('vat_percentage', 7, 4)->default(12)->after('amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'vat_percentage')) {
                $table->dropColumn('vat_percentage');
            }
        });
    }
};
