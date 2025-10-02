<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Gateway;

class GatewaysSeeder extends Seeder
{
    public function run(): void
    {
        Gateway::create([
            'nome' => 'Estacao Fiscal SP',
            'ativo' => true,
            'key_id' => 'gw-sp-001',
            'key_alg' => 'aes-256-gcm',
            'key_material_encrypted' => base64_encode(random_bytes(24)),
            'observacoes' => 'Gateway de homologação SP',
        ]);

        Gateway::create([
            'nome' => 'Estacao Fiscal RJ',
            'ativo' => true,
            'key_id' => 'gw-rj-001',
            'key_alg' => 'aes-256-gcm',
            'key_material_encrypted' => base64_encode(random_bytes(24)),
            'observacoes' => 'Gateway de homologação RJ',
        ]);
    }
}
