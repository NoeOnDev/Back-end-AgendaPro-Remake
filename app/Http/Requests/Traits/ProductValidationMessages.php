<?php

namespace App\Http\Requests\Traits;

trait ProductValidationMessages
{
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del producto es obligatorio',
            'price.required' => 'El precio del producto es obligatorio',
            'price.min' => 'El precio no puede ser negativo',
            'stock.min' => 'El stock no puede ser negativo',
        ];
    }
}
