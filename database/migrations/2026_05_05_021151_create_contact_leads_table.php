<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('lastname')->nullable();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('message');
            $table->string('source')->default('contact_form');
            $table->string('locale', 5)->default('es');
            $table->string('ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->boolean('is_read')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->index(['is_read', 'is_archived', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_leads');
    }
};
