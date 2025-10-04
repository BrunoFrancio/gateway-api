<?php

namespace App\Http\Requests\Gateway;

use Illuminate\Foundation\Http\FormRequest;

class StoreGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome'        => ['required', 'string', 'max:150', 'unique:gateways,nome'],
            'ativo'       => ['sometimes', 'boolean'],
            'observacoes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'O campo nome é obrigatório.',
            'nome.unique'   => 'Já existe um gateway com este nome.',
        ];
    }
}
