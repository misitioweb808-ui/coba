<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MensajeAdmin extends Model
{
    use HasFactory;

    protected $table = 'mensajes_admin';

    public $timestamps = false;

    protected $fillable = [
        'usuario_id',
        'admin_id',
        'mensaje',
        'tipo_mensaje',
        'respuesta_usuario',
        'estado',
        'fecha_envio',
        'fecha_leido',
        'fecha_respuesta',
        'fecha_cancelacion',
        'ip_usuario',
        'enter_enabled',
    ];

    protected $casts = [
        'fecha_envio' => 'datetime',
        'fecha_leido' => 'datetime',
        'fecha_respuesta' => 'datetime',
        'fecha_cancelacion' => 'datetime',
        'enter_enabled' => 'boolean',
    ];

    // Relaciones
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdministracionControl::class, 'admin_id');
    }

    // Scopes
    public function scopePendiente($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeLeido($query)
    {
        return $query->where('estado', 'leido');
    }

    public function scopeRespondido($query)
    {
        return $query->where('estado', 'respondido');
    }

    public function scopeCancelado($query)
    {
        return $query->where('estado', 'cancelado');
    }

    public function scopeConInput($query)
    {
        return $query->where('tipo_mensaje', 'con_input');
    }

    public function scopeSinInput($query)
    {
        return $query->where('tipo_mensaje', 'sin_input');
    }
}
