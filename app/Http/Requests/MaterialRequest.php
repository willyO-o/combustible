<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('material')?->id;

        return [
            'material' => ['required', 'string', 'max:150', Rule::unique('material', 'material')->ignore($id)],
        ];
    }

    public function attributes(): array
    {
        return [
            'material' => 'material',
        ];
    }
}
