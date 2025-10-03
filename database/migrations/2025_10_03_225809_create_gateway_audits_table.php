<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('gateway_audits', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('gateway_id')->index();
            $table->string('acao');
            $table->string('old_key_id')->nullable();
            $table->string('new_key_id')->nullable();
            $table->unsignedBigInteger('ator_id')->nullable();
            $table->string('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->foreign('gateway_id')->references('id')->on('gateways')->cascadeOnDelete();
        });
    }

    public function down(): void {
        Schema::dropIfExists('gateway_audits');
    }
};
