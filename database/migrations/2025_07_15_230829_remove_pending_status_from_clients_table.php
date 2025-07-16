<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // Added this import for DB facade

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, update any existing 'pending' clients to 'inactive'
        DB::table('clients')->where('status', 'pending')->update(['status' => 'inactive']);
        
        // Then modify the enum to remove 'pending'
        Schema::table('clients', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive'])->default('active')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive', 'pending'])->default('active')->change();
        });
    }
};
