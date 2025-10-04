<?php

namespace App\Http\Requests\Gateway;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => [
                'required',
                'string',
                'min:3',
                'max:100',
                'regex:/^[a-z0-9\-_]+$/',
                Rule::unique('gateways', 'nome'),
            ],
            'ativo' => 'boolean',
            'observacoes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'O nome do gateway é obrigatório.',
            'nome.regex' => 'O nome deve conter apenas letras minúsculas, números, hífen e underscore.',
            'nome.unique' => 'Já existe um gateway com este nome.',
        ];
    }
}
