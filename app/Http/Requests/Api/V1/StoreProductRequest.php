<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Traits\ProductValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    use ProductValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'active' => 'boolean'
        ];
    }
}
