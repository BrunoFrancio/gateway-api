<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gateway_sql_jobs', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('gateway_id');
            $table->foreign('gateway_id')->references('id')->on('gateways')->cascadeOnDelete();

            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('atualizado_por')->nullable()->constrained('users')->nullOnDelete();

            $table->string('key_id')->index();
            $table->string('transit_alg')->index();

            $table->text('sql_ciphertext');
            $table->string('nonce');
            $table->string('tag')->nullable();

            // Lifecycle do job
            $table->string('status')->default('pending')->index(); // pending|sent|ack|failed|canceled
            $table->unsignedInteger('tentativas')->default(0);
            $table->text('ultima_falha')->nullable();
            $table->timestamp('disponivel_em')->nullable()->index();

            $table->timestamps();

            $table->index(['gateway_id', 'status']);
            $table->index(['status', 'disponivel_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_sql_jobs');
    }
};
