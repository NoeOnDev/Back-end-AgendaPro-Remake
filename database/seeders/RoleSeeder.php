<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'owner',
                'display_name' => 'Propietario',
                'description' => 'Acceso completo al proyecto',
                'is_system' => true,
                'permissions' => [
                    'contacts' => ['view', 'create', 'edit', 'delete'],
                    'appointments' => ['view', 'create', 'edit', 'delete', 'assign'],
                    'users' => ['view', 'invite', 'remove', 'manage_roles'],
                    'project' => ['view', 'edit', 'delete', 'settings'],
                    'reports' => ['view', 'export']
                ]
            ],
            [
                'name' => 'admin',
                'display_name' => 'Administrador',
                'description' => 'Gestión completa excepto eliminar proyecto',
                'is_system' => true,
                'permissions' => [
                    'contacts' => ['view', 'create', 'edit', 'delete'],
                    'appointments' => ['view', 'create', 'edit', 'delete', 'assign'],
                    'users' => ['view', 'invite', 'remove'],
                    'project' => ['view', 'edit', 'settings'],
                    'reports' => ['view', 'export']
                ]
            ],
            [
                'name' => 'editor',
                'display_name' => 'Editor',
                'description' => 'Puede gestionar contactos y citas',
                'is_system' => true,
                'permissions' => [
                    'contacts' => ['view', 'create', 'edit', 'delete'],
                    'appointments' => ['view', 'create', 'edit', 'delete'],
                    'users' => ['view'],
                    'project' => ['view'],
                    'reports' => ['view']
                ]
            ],
            [
                'name' => 'scheduler',
                'display_name' => 'Agendador',
                'description' => 'Solo puede crear y gestionar citas',
                'is_system' => true,
                'permissions' => [
                    'contacts' => ['view'],
                    'appointments' => ['view', 'create', 'edit'],
                    'users' => ['view'],
                    'project' => ['view'],
                    'reports' => ['view']
                ]
            ],
            [
                'name' => 'viewer',
                'display_name' => 'Solo lectura',
                'description' => 'Solo puede ver información',
                'is_system' => true,
                'permissions' => [
                    'contacts' => ['view'],
                    'appointments' => ['view'],
                    'users' => ['view'],
                    'project' => ['view'],
                    'reports' => ['view']
                ]
            ]
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }
    }
}
