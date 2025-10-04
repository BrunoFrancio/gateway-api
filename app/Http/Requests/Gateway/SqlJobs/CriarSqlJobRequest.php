<?php

namespace App\Http\Requests\Gateway\SqlJobs;

use Illuminate\Foundation\Http\FormRequest;

class CriarSqlJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sql'           => ['required', 'string', 'min:1'],
            'disponivel_em' => ['nullable', 'date'],
        ];
    }
}
