<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->string('nickname')->nullable();
            $table->string('street_address');
            $table->string('street_address_2')->nullable();
            $table->string('city');
            $table->string('state');
            $table->string('zip_code');
            $table->json('photos')->nullable(); // Array of photo paths
            $table->enum('access', ['residential', 'commercial'])->default('residential');
            $table->enum('pool_type', ['fiberglass', 'vinyl_liner', 'concrete', 'gunite'])->nullable();
            $table->enum('water_type', ['chlorine', 'salt'])->default('chlorine');
            $table->string('filter_type')->nullable();
            $table->enum('setting', ['indoor', 'outdoor'])->default('outdoor');
            $table->enum('installation', ['inground', 'above'])->default('inground');
            $table->integer('gallons')->nullable();
            $table->enum('service_frequency', ['semi_weekly', 'weekly', 'bi_weekly', 'monthly'])->default('weekly');
            $table->string('service_day_1')->nullable();
            $table->string('service_day_2')->nullable();
            $table->decimal('rate_per_visit', 8, 2)->nullable();
            $table->boolean('chemicals_included')->default(true);
            $table->foreignId('assigned_technician_id')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('is_favorite')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
