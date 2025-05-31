<?php

namespace Database\Seeders;

use App\Models\BusinessType;
use App\Models\FormTemplate;
use Illuminate\Database\Seeder;

class BusinessTypeSeeder extends Seeder
{
    public function run(): void
    {
        $businessTypes = [
            [
                'name' => 'Barbería',
                'description' => 'Negocio especializado en cortes de cabello y arreglo de barba',
                'icon' => 'scissors',
                'template' => [
                    'name' => 'Formulario de Atención - Barbería',
                    'description' => 'Formulario estándar para registrar servicios de barbería',
                    'fields' => [
                        [
                            'name' => 'servicio_realizado',
                            'label' => 'Servicio Realizado',
                            'type' => 'select',
                            'options' => ['Corte', 'Barba', 'Corte + Barba', 'Lavado'],
                            'required' => true
                        ],
                        [
                            'name' => 'tipo_corte',
                            'label' => 'Tipo de Corte',
                            'type' => 'text',
                            'required' => false,
                            'placeholder' => 'Ej: Degradado, Clásico'
                        ],
                        [
                            'name' => 'productos_utilizados',
                            'label' => 'Productos Utilizados',
                            'type' => 'checkbox',
                            'options' => ['Gel', 'Pomada', 'Cera', 'Aceite para barba'],
                            'required' => false
                        ],
                        [
                            'name' => 'notas_especiales',
                            'label' => 'Notas Especiales',
                            'type' => 'textarea',
                            'required' => false,
                            'placeholder' => 'Alergias, preferencias, observaciones...'
                        ],
                        [
                            'name' => 'precio_final',
                            'label' => 'Precio Final',
                            'type' => 'number',
                            'required' => true,
                            'min' => 0
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Salón de Belleza',
                'description' => 'Servicios de belleza integral',
                'icon' => 'sparkles'
            ],
            [
                'name' => 'Consultorio Médico',
                'description' => 'Consultas y tratamientos médicos',
                'icon' => 'medical'
            ],
            [
                'name' => 'Otro',
                'description' => 'Tipo de negocio personalizado',
                'icon' => 'business'
            ]
        ];

        foreach ($businessTypes as $typeData) {
            $businessType = BusinessType::create([
                'name' => $typeData['name'],
                'description' => $typeData['description'],
                'icon' => $typeData['icon']
            ]);

            if (isset($typeData['template'])) {
                FormTemplate::create([
                    'business_type_id' => $businessType->id,
                    'name' => $typeData['template']['name'],
                    'description' => $typeData['template']['description'],
                    'fields' => $typeData['template']['fields'],
                    'is_default' => true
                ]);
            }
        }
    }
}
