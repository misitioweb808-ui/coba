<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HerramientaEnviada extends Model
{
    use HasFactory;

    protected $table = 'herramientas_enviadas';

    public $timestamps = false;

    protected $fillable = [
        'usuario_id',
        'admin_id',
        'pagina',
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
}
