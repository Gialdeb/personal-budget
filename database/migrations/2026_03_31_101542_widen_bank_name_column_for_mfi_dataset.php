<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->string('name', 200)->change();
        });
    }

    public function down(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->string('name', 150)->change();
        });
    }
};
