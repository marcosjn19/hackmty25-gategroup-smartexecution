<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('models', function (Blueprint $table) {
            $table->decimal('efficiency', 5, 2)->nullable()->change();
            $table->unsignedInteger('eta')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('models', function (Blueprint $table) {
            // Revertir a NOT NULL con default para evitar errores al revertir
            $table->decimal('efficiency', 5, 2)->default(0)->nullable(false)->change();
            $table->unsignedInteger('eta')->default(0)->nullable(false)->change();
        });
    }
};
