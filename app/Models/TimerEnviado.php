<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimerEnviado extends Model
{
    use HasFactory;

    protected $table = 'timers_enviados';

    public $timestamps = false;

    protected $fillable = [
        'usuario_id',
        'admin_id',
        'tiempo_segundos',
        'mensaje_personalizado',
        'estado',
        'fecha_envio',
        'fecha_visto',
        'fecha_cerrado',
        'ip_usuario',
    ];

    protected $casts = [
        'fecha_envio' => 'datetime',
        'fecha_visto' => 'datetime',
        'fecha_cerrado' => 'datetime',
        'tiempo_segundos' => 'integer',
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

    public function scopeVisto($query)
    {
        return $query->where('estado', 'visto');
    }

    public function scopeCerrado($query)
    {
        return $query->where('estado', 'cerrado');
    }

    // Accessors
    public function getTiempoFormateadoAttribute(): string
    {
        $minutos = floor($this->tiempo_segundos / 60);
        $segundos = $this->tiempo_segundos % 60;
        
        return sprintf('%02d:%02d', $minutos, $segundos);
    }
}
