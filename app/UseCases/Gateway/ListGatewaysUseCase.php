<?php

namespace App\UseCases\Gateway;

use App\Models\Gateway;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListGatewaysUseCase
{
    public function executar(?string $ativoFiltro, ?string $termoBusca, int $porPagina = 15): array
    {
        $porPagina = min(max($porPagina, 1), 100);

        $consulta = Gateway::query();

        if ($ativoFiltro !== null && $ativoFiltro !== '') {
            $valor = strtolower((string) $ativoFiltro);
            if (in_array($valor, ['1','true','on','yes'], true)) {
                $consulta->where('ativo', true);
            } elseif (in_array($valor, ['0','false','off','no'], true)) {
                $consulta->where('ativo', false);
            }
        }

        if ($termoBusca !== null && $termoBusca !== '') {
            $consulta->where('nome', 'ilike', '%' . str_replace('%', '\%', trim($termoBusca)) . '%');
        }

        $paginado = $consulta->orderBy('nome')->paginate($porPagina)->withQueryString();

        $itens = collect($paginado->items())->map(function ($gateway) {
            $dados = $gateway->toArray();
            unset($dados['key_material_encrypted']);
            return $dados;
        })->values()->all();

        return [$paginado, $itens];
    }
}
