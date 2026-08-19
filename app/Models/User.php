<?php

namespace App\Models;

use App\Enums\AptitudFisica;
use App\Enums\Genero;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Usuario de la plataforma (aportante / creador / moderador).
 *
 * Roles Spatie: admin, moderator, voluntario, member.
 * El creador de iniciativas es un member (o voluntario) con iniciativas propias.
 * Moderadores/voluntarios pueden tener N municipios vía usuario_municipio (P20).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $celular
 * @property int|null $zona_id
 * @property int|null $municipio_id
 * @property string|null $barrio
 * @property Genero|null $genero
 * @property int|null $edad
 * @property AptitudFisica|null $aptitud_fisica
 * @property string|null $notas_salud
 * @property string|null $avatar_path
 * @property string|null $inicial
 * @property string|null $google_id
 * @property \Illuminate\Support\Carbon|null $acepta_terminos_at
 * @property \Illuminate\Support\Carbon|null $acepta_descargo_at
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Guard Spatie para checks de rol/permiso con tokens Sanctum.
     */
    protected string $guard_name = 'web';

    /**
     * Asignación masiva explícita del perfil.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'celular',
        'zona_id',
        'municipio_id',
        'barrio',
        'genero',
        'edad',
        'aptitud_fisica',
        'notas_salud',
        'avatar_path',
        'inicial',
        'google_id',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
    ];

    /**
     * Casts explícitos de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'zona_id' => 'integer',
            'municipio_id' => 'integer',
            'genero' => Genero::class,
            'edad' => 'integer',
            'aptitud_fisica' => AptitudFisica::class,
            'acepta_terminos_at' => 'datetime',
            'acepta_descargo_at' => 'datetime',
        ];
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    /**
     * Municipios asignados (moderación / voluntariado territorial).
     */
    public function municipiosAsignados(): BelongsToMany
    {
        return $this->belongsToMany(Municipio::class, 'usuario_municipio')
            ->withTimestamps();
    }

    /**
     * @return list<int>
     */
    public function assignedMunicipioIds(): array
    {
        if ($this->relationLoaded('municipiosAsignados')) {
            return $this->municipiosAsignados->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return $this->municipiosAsignados()->pluck('municipios.id')->map(fn ($id) => (int) $id)->all();
    }

    public function isPlatformAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Admin: cualquier municipio. Moderador/voluntario: solo los asignados.
     */
    public function canAccessMunicipio(?int $municipioId): bool
    {
        if ($this->isPlatformAdmin()) {
            return true;
        }

        if ($municipioId === null) {
            return false;
        }

        return in_array($municipioId, $this->assignedMunicipioIds(), true);
    }

    /**
     * Moderación de una iniciativa: admin libre; moderador solo si el municipio está asignado.
     */
    public function canModerateIniciativa(Iniciativa $iniciativa): bool
    {
        if (! $this->can('iniciativas.moderate')) {
            return false;
        }

        if ($this->isPlatformAdmin()) {
            return true;
        }

        return $this->canAccessMunicipio($iniciativa->municipio_id);
    }

    /**
     * [P53] Criterio simple de onboarding pendiente para el perfil
     * comunitario: falta `municipio_id`, o el usuario no tiene ninguna
     * habilidad NI ninguna disponibilidad asignada.
     *
     * Es una decisión de producto deliberadamente simple (no hay una
     * definición más estricta todavía) — puede ajustarse más adelante sin
     * romper el contrato del payload (`needs_onboarding` sigue siendo un
     * booleano expuesto por todos los endpoints que devuelven el usuario:
     * register, login, completarRegistro, exchange, profile show/update).
     */
    public function needsOnboarding(): bool
    {
        if ($this->municipio_id === null) {
            return true;
        }

        $habilidadesCount = $this->relationLoaded('habilidades')
            ? $this->habilidades->count()
            : $this->habilidades()->count();

        $disponibilidadesCount = $this->relationLoaded('disponibilidades')
            ? $this->disponibilidades->count()
            : $this->disponibilidades()->count();

        return $habilidadesCount === 0 && $disponibilidadesCount === 0;
    }

    public function habilidades(): BelongsToMany
    {
        return $this->belongsToMany(Habilidad::class)->withTimestamps();
    }

    public function disponibilidades(): BelongsToMany
    {
        return $this->belongsToMany(Disponibilidad::class)->withTimestamps();
    }

    public function iniciativas(): HasMany
    {
        return $this->hasMany(Iniciativa::class);
    }

    public function aportes(): HasMany
    {
        return $this->hasMany(Aporte::class);
    }

    public function profesional(): HasOne
    {
        return $this->hasOne(Profesional::class);
    }

    public function notificacionPreferencia(): HasOne
    {
        return $this->hasOne(NotificacionPreferencia::class);
    }

    public function documentoLegalAceptaciones(): HasMany
    {
        return $this->hasMany(DocumentoLegalAceptacion::class);
    }
}
