<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        DB::table('brands')->whereNotNull('category')->where('category', '<>', '')
            ->select('category')->distinct()->orderBy('category')->get()->each(function (object $brand): void {
                DB::table('brand_categories')->insert([
                    'uuid' => (string) Str::uuid(), 'name' => $brand->category,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_categories');
    }
};
