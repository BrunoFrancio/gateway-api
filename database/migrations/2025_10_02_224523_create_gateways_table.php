<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gateways', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('nome')->unique();
            $table->boolean('ativo')->default(true)->index();

            $table->string('key_id')->nullable()->index();
            $table->string('key_alg')->nullable()->index();
            $table->text('key_material_encrypted')->nullable();

            $table->timestamp('key_rotated_at')->nullable()->index();
            $table->timestamp('last_seen_at')->nullable()->index();

            $table->text('observacoes')->nullable();

            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('atualizado_por')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['key_id', 'key_alg']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateways');
    }
};
