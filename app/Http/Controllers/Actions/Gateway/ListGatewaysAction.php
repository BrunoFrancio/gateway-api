<?php

namespace App\Http\Controllers\Actions\Gateway;

use App\UseCases\Gateway\ListGatewaysUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListGatewaysAction
{
    public function __construct(private ListGatewaysUseCase $casoDeUso) {}

    public function __invoke(Request $requisicao): JsonResponse
    {
        // Normaliza filtro "active" (true/false|null)
        $ativoFiltroNormalizado = null;
        $valorBrutoAtivo = $requisicao->query('active');
        if ($valorBrutoAtivo !== null && $valorBrutoAtivo !== '') {
            $ativoFiltroNormalizado = filter_var(
                $valorBrutoAtivo,
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );
        }

        // Normaliza "per_page" (limite de 1..100)
        $porPagina = (int) $requisicao->query('per_page', 15);
        $porPagina = max(1, min($porPagina, 100));

        // Normaliza termo de busca
        $termoDeBusca = (string) $requisicao->query('search', '');
        $termoDeBusca = $termoDeBusca !== '' ? $termoDeBusca : null;

        [$paginador, $itens] = $this->casoDeUso->executar(
            ativoFiltro: $ativoFiltroNormalizado,
            termoBusca: $termoDeBusca,
            porPagina: $porPagina
        );

        return response()->json([
            'data' => $itens,
            'meta' => [
                'current_page' => $paginador->currentPage(),
                'per_page'     => $paginador->perPage(),
                'total'        => $paginador->total(),
                'last_page'    => $paginador->lastPage(),
                'from'         => $paginador->firstItem(),
                'to'           => $paginador->lastItem(),
            ],
            'links' => [
                'first' => $paginador->url(1),
                'last'  => $paginador->url($paginador->lastPage()),
                'prev'  => $paginador->previousPageUrl(),
                'next'  => $paginador->nextPageUrl(),
            ],
        ]);
    }
}
