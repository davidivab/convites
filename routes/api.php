<?php

use App\Http\Controllers\Api\AporteController;
use App\Http\Controllers\Api\AdminIniciativaController;
use App\Http\Controllers\Api\AdminSolicitudRolController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CentroController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\IniciativaController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\ModeracionIniciativaController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfesionalController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SolicitudRolController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])
        ->middleware('throttle:10,1');
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });

    // P42: login/registro con Google.
    Route::prefix('google')->group(function (): void {
        Route::get('redirect', [GoogleAuthController::class, 'redirect']);
        Route::get('callback', [GoogleAuthController::class, 'callback']);
        Route::post('exchange', [GoogleAuthController::class, 'exchange'])
            ->middleware('throttle:20,1');
        Route::post('completar-registro', [GoogleAuthController::class, 'completarRegistro'])
            ->middleware('throttle:10,1');
    });
});

/*
|--------------------------------------------------------------------------
| Catálogos públicos
|--------------------------------------------------------------------------
*/
Route::prefix('catalogos')->group(function (): void {
    Route::get('zonas', [CatalogController::class, 'zonas']);
    Route::get('departamentos', [CatalogController::class, 'departamentos']);
    Route::get('municipios', [CatalogController::class, 'municipios']);
    Route::get('categorias', [CatalogController::class, 'categorias']);
    Route::get('habilidades', [CatalogController::class, 'habilidades']);
    Route::get('disponibilidades', [CatalogController::class, 'disponibilidades']);
    Route::get('documentos-legales', [CatalogController::class, 'documentosLegales']);
});

/*
|--------------------------------------------------------------------------
| Lectura pública (exploración)
|--------------------------------------------------------------------------
*/
Route::get('iniciativas', [IniciativaController::class, 'index'])
    ->middleware('throttle:60,1');
Route::get('iniciativas/mapa', [IniciativaController::class, 'mapa'])
    ->middleware('throttle:60,1');
Route::get('iniciativas/{slug}', [IniciativaController::class, 'show'])
    ->middleware(['auth.optional', 'throttle:60,1']);

// "¿Tengo este material, quién lo necesita?" — búsqueda inversa por ítem.
Route::get('materiales', [MaterialController::class, 'index'])
    ->middleware('throttle:60,1');

Route::get('centros', [CentroController::class, 'index'])
    ->middleware('throttle:60,1');
Route::get('centros/{centro}', [CentroController::class, 'show'])
    ->middleware('throttle:60,1');

Route::get('profesionales', [ProfesionalController::class, 'index'])
    ->middleware('throttle:60,1');
Route::get('profesionales/{profesional}', [ProfesionalController::class, 'show'])
    ->middleware('throttle:60,1');

/*
|--------------------------------------------------------------------------
| Geo (proxy Nominatim)
|--------------------------------------------------------------------------
*/
Route::prefix('geo')->group(function (): void {
    Route::get('search', [\App\Http\Controllers\Api\GeoController::class, 'search'])
        ->middleware('throttle:20,1');
    Route::get('reverse', [\App\Http\Controllers\Api\GeoController::class, 'reverse'])
        ->middleware('throttle:30,1');
});

/*
|--------------------------------------------------------------------------
| Autenticado
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/dashboard', function () {
        return response()->json(['message' => 'Dashboard OK']);
    })->middleware('permission:dashboard.view');

    Route::get('/profile', [ProfileController::class, 'show'])
        ->middleware('permission:profile.view');
    Route::put('/profile', [ProfileController::class, 'update'])
        ->middleware('permission:profile.update');

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead']);

    Route::get('/users', function () {
        return response()->json(['message' => 'Users OK']);
    })->middleware('permission:users.view');

    // Mis recursos
    Route::get('mis/iniciativas', [IniciativaController::class, 'mine'])
        ->middleware('permission:iniciativas.view');
    Route::get('mis/aportes', [AporteController::class, 'mine'])
        ->middleware('permission:aportes.view_own');

    // P46: solicitar rol moderador/voluntario (todo ciudadano autenticado).
    Route::post('solicitudes-rol', [SolicitudRolController::class, 'store']);
    Route::get('mis-solicitudes-rol', [SolicitudRolController::class, 'mine']);

    // Iniciativas (crear / editar propias)
    Route::post('iniciativas', [IniciativaController::class, 'store'])
        ->middleware(['permission:iniciativas.create', 'throttle:30,1']);
    Route::put('iniciativas/{iniciativa}', [IniciativaController::class, 'update'])
        ->middleware('permission:iniciativas.update_own|iniciativas.moderate');
    Route::post('iniciativas/{iniciativa}/enviar-revision', [IniciativaController::class, 'enviarRevision'])
        ->middleware('permission:iniciativas.create');
    Route::post('iniciativas/{iniciativa}/cerrar', [IniciativaController::class, 'cerrar'])
        ->middleware('permission:iniciativas.update_own|iniciativas.moderate');

    // Aportes
    Route::post('iniciativas/{iniciativa}/aportes', [AporteController::class, 'store'])
        ->middleware(['permission:aportes.create', 'throttle:30,1']);
    Route::get('iniciativas/{iniciativa}/aportantes', [AporteController::class, 'porIniciativa'])
        ->middleware('permission:iniciativas.create|iniciativas.moderate');
    Route::post('aportes/{aporte}/recepcion', [AporteController::class, 'marcarRecepcion'])
        ->middleware(['permission:iniciativas.create|iniciativas.moderate', 'throttle:30,1']);
    Route::delete('aportes/{aporte}/evidencia', [AporteController::class, 'eliminarEvidencia'])
        ->middleware('permission:iniciativas.create|iniciativas.moderate');
    Route::post('aportes/{aporte}/cancelar', [AporteController::class, 'cancel'])
        ->middleware('permission:aportes.view_own');

    // Profesionales
    Route::post('profesionales', [ProfesionalController::class, 'register'])
        ->middleware(['permission:profesionales.register', 'throttle:10,1']);
    Route::post('profesionales/{profesional}/solicitudes', [ProfesionalController::class, 'contact'])
        ->middleware(['permission:profesionales.contact', 'throttle:20,1']);

    // Mi perfil profesional (P29) — el propio profesional gestiona lo suyo.
    Route::middleware('permission:profesional_perfil.view_own')->group(function (): void {
        Route::get('mi-perfil-profesional', [ProfesionalController::class, 'miPerfil']);
        Route::get('mi-perfil-profesional/solicitudes', [ProfesionalController::class, 'misSolicitudes']);
    });
    Route::put('mi-perfil-profesional', [ProfesionalController::class, 'actualizarMiPerfil'])
        ->middleware('permission:profesional_perfil.update_own');
    Route::patch('mi-perfil-profesional/solicitudes/{solicitud}', [ProfesionalController::class, 'actualizarSolicitud'])
        ->middleware('permission:profesional_perfil.update_own');

    // Moderación
    Route::prefix('moderacion')->group(function (): void {
        Route::middleware('permission:iniciativas.moderate')->group(function (): void {
            Route::get('iniciativas', [ModeracionIniciativaController::class, 'index']);
            Route::post('iniciativas/{iniciativa}/aprobar', [ModeracionIniciativaController::class, 'aprobar']);
            Route::post('iniciativas/{iniciativa}/rechazar', [ModeracionIniciativaController::class, 'rechazar']);
            Route::post('iniciativas/{iniciativa}/solicitar-cambios', [ModeracionIniciativaController::class, 'solicitarCambios']);
            Route::post('iniciativas/{iniciativa}/cerrar', [ModeracionIniciativaController::class, 'cerrar']);
        });

        Route::middleware('permission:profesionales.moderate')->group(function (): void {
            Route::get('profesionales', [ProfesionalController::class, 'moderationQueue']);
            Route::post('profesionales/{profesional}/aprobar', [ProfesionalController::class, 'aprobar']);
            Route::post('profesionales/{profesional}/rechazar', [ProfesionalController::class, 'rechazar']);
            Route::post('profesionales/{profesional}/solicitar-cambios', [ProfesionalController::class, 'solicitarCambios']);
        });
    });

    // Gestión de centros
    Route::middleware('permission:centros.manage')->group(function (): void {
        Route::post('centros', [CentroController::class, 'store']);
        Route::put('centros/{centro}', [CentroController::class, 'update']);
        Route::delete('centros/{centro}', [CentroController::class, 'destroy']);
    });

    // Admin: usuarios moderador/voluntario + municipios + auditoría de convites
    Route::middleware('permission:users.manage')->prefix('admin')->group(function (): void {
        Route::get('users', [AdminUserController::class, 'index']);
        Route::post('users', [AdminUserController::class, 'store'])
            ->middleware('throttle:20,1');
        Route::put('users/{user}/municipios', [AdminUserController::class, 'syncMunicipios']);

        Route::get('iniciativas', [AdminIniciativaController::class, 'index']);
        Route::get('iniciativas/{slug}', [AdminIniciativaController::class, 'show']);
        Route::get('iniciativas/{slug}/aportes', [AdminIniciativaController::class, 'aportes']);

        // P46: cola de solicitudes de rol (moderador/voluntario).
        Route::get('solicitudes-rol', [AdminSolicitudRolController::class, 'index']);
        Route::post('solicitudes-rol/{solicitud}/aprobar', [AdminSolicitudRolController::class, 'aprobar']);
        Route::post('solicitudes-rol/{solicitud}/rechazar', [AdminSolicitudRolController::class, 'rechazar']);
    });
});
