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
        Schema::create('work_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users');
            $table->string('title');
            $table->text('notes')->nullable();
            $table->string('status');
            $table->string('visibility');
            $table->text('blurb')->nullable();
            $table->unsignedSmallInteger('effort_days')->nullable();
            $table->unsignedSmallInteger('elapsed_days')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('due_on')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_items');
    }
};
