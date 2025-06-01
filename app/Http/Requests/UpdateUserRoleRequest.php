<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role_id' => 'required|exists:roles,id'
        ];
    }

    public function messages(): array
    {
        return [
            'role_id.required' => 'Debes seleccionar un rol.',
            'role_id.exists' => 'El rol seleccionado no es válido.'
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Verificar que no sea rol de owner
            if ($this->role_id) {
                $role = \App\Models\Role::find($this->role_id);
                if ($role && $role->name === 'owner') {
                    $validator->errors()->add('role_id', 'No se puede asignar el rol de propietario.');
                }
            }
        });
    }
}
