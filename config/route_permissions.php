<?php

/**
 * Catálogo de roles y permisos API (SSOT).
 * Sembrado por Database\Seeders\RolesAndPermissionsSeeder.
 *
 * Convención: rutas protegidas usan middleware('permission:<nombre>').
 */
return [
    'roles' => [
        'admin',
        'moderator',
        'voluntario',
        'member',
        'profesional',
    ],

    /**
     * Permisos por rol (admin recibe el catálogo completo al final del seeder).
     *
     * Decisión P19: `voluntario` es un ROL NUEVO (no renombrar `member`).
     * - `member` = auto-registro público (aportante / creador).
     * - `voluntario` = cuenta creada por admin, con municipios asignados (P20);
     *   mismos permisos de participación comunitaria que member, sin moderar.
     * - `moderator` = cola de moderación scoped a sus municipios (P21).
     */
    'role_permissions' => [
        'member' => [
            'dashboard.view',
            'profile.view',
            'profile.update',
            'iniciativas.view',
            'iniciativas.create',
            'iniciativas.update_own',
            'aportes.create',
            'aportes.view_own',
            'centros.view',
            'profesionales.view',
            'profesionales.register',
            'profesionales.contact',
        ],
        'voluntario' => [
            'dashboard.view',
            'profile.view',
            'profile.update',
            'iniciativas.view',
            'iniciativas.create',
            'iniciativas.update_own',
            'aportes.create',
            'aportes.view_own',
            'centros.view',
            'profesionales.view',
            'profesionales.contact',
        ],
        'moderator' => [
            'dashboard.view',
            'profile.view',
            'profile.update',
            'iniciativas.view',
            'iniciativas.moderate',
            'profesionales.view',
            'profesionales.moderate',
            'centros.view',
            'centros.manage',
            'legal.manage',
        ],
        // P29: asignado junto con `member` cuando el usuario registra un perfil
        // profesional (ProfesionalController::register) — nunca reemplaza al rol
        // que ya tenía, un usuario puede ser member+profesional a la vez.
        'profesional' => [
            'dashboard.view',
            'profile.view',
            'profile.update',
            'profesional_perfil.view_own',
            'profesional_perfil.update_own',
        ],
    ],

    /**
     * Solo admin (también se suman al catálogo completo del admin).
     */
    'admin_permissions' => [
        'users.view',
        'users.manage',
        'roles.view',
        'roles.manage',
        'catalogos.manage',
        'legal.manage',
        'notifications.manage',
    ],
];
