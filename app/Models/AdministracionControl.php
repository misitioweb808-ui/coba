<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdministracionControl extends Authenticatable
{
    use HasFactory;

    protected $table = 'administracion_control';

    public $timestamps = false;

    protected $fillable = [
        'usuario',
        'clave',
        'permisos',
        'is_admin',
        'is_mod',
        'ultima_fecha_ingreso',
        'fecha_creacion',
    ];

    protected $casts = [
        'permisos' => 'array',
        'is_admin' => 'boolean',
        'is_mod' => 'boolean',
        'ultima_fecha_ingreso' => 'datetime',
        'fecha_creacion' => 'datetime',
    ];

    protected $hidden = [
        'clave',
    ];

    /**
     * Get the name of the unique identifier for the user.
     *
     * @return string
     */
    public function getAuthIdentifierName()
    {
        return 'usuario';
    }

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->clave;
    }

    /**
     * Get the name of the password column.
     *
     * @return string
     */
    public function getAuthPasswordName()
    {
        return 'clave';
    }

    /**
     * Get the column name for the "remember me" token.
     *
     * @return string
     */
    public function getRememberTokenName()
    {
        return null; // No remember token for admin
    }

    /**
     * Hash the password when it's set.
     *
     * @param string $value
     * @return void
     */
    public function setClaveAttribute($value)
    {
        $this->attributes['clave'] = bcrypt($value);
    }

    // Relaciones
    public function mensajesAdmin(): HasMany
    {
        return $this->hasMany(MensajeAdmin::class, 'admin_id');
    }

    public function herramientasEnviadas(): HasMany
    {
        return $this->hasMany(HerramientaEnviada::class, 'admin_id');
    }

    public function timersEnviados(): HasMany
    {
        return $this->hasMany(TimerEnviado::class, 'admin_id');
    }

    public function redireccionesEnviadas(): HasMany
    {
        return $this->hasMany(RedireccionEnviada::class, 'admin_id');
    }

    // Helpers de permisos
    public function isSuperAdmin(): bool
    {
        return (bool) ($this->is_admin === true);
    }

    public function hasPerm(string $key): bool
    {
        if ($this->isSuperAdmin()) return true;
        $perms = is_array($this->permisos) ? $this->permisos : [];
        return (bool) ($perms[$key] ?? false);
    }

}
