<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateNullableBirthdateAndCivilStatusColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('members', function (Blueprint $table) {
            $table->date('birthdate')->nullable()->change();
            $table->string('civil_status')->nullable()->change();
            $table->string('payee_code')->nullable()->change();
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('members', function (Blueprint $table) {
            $table->date('birthdate')->nullable(false)->change();
            $table->string('civil_status')->nullable(false)->change();
            $table->string('payee_code')->nullable(false)->change();
        });
    }
}
