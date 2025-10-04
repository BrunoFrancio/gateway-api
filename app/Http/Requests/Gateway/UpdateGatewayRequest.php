<?php

namespace App\Http\Requests\Gateway;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $gateway = $this->route('gateway');

        return [
            'nome'        => ['sometimes', 'string', 'max:150', 'unique:gateways,nome,' . ($gateway?->id ?? 'null')],
            'ativo'       => ['sometimes', 'boolean'],
            'observacoes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
