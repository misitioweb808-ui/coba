<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstatusUsuario extends Model
{
    use HasFactory;

    protected $table = 'estatus_usuarios';

    public $timestamps = false;

    protected $fillable = [
        'usuario_id',
        'ip_real',
        'estado',
        'pagina_actual',
        'ultimo_heartbeat',
        'fecha_conexion',
        'user_agent',
    ];

    protected $casts = [
        'ultimo_heartbeat' => 'datetime',
        'fecha_conexion' => 'datetime',
    ];

    // Relaciones
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    // Scopes
    public function scopeOnline($query)
    {
        return $query->where('estado', 'online');
    }

    public function scopeInactive($query)
    {
        return $query->where('estado', 'inactive');
    }

    public function scopeOffline($query)
    {
        return $query->where('estado', 'offline');
    }
}
