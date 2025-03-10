<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('model_audits', function (Blueprint $table) {
            $table->id();
            $table->string('model_type'); // Model class name
            $table->unsignedBigInteger('model_id')->nullable(); // Model primary key
            $table->string('event'); // Created, updated, deleted
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->unsignedBigInteger('user_id')->nullable(); // Authenticated user
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_audits');
    }
};
