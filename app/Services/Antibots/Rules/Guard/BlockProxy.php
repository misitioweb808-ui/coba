<?php

namespace App\Services\Antibots\Rules\Guard;

use Illuminate\Support\Facades\Http;

class BlockProxy
{
    public array $ignoreList = [
        '91.108.5.7',
        '205.210.31.19',
        '91.108.5.49',
        '40.113.118.83',
    ];

    public function run()
    {
        $ip = request()->ip();
        $userAgent = request()->userAgent();

        // Validar que tenemos una IP válida
        if (!$ip || $ip === '127.0.0.1' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return; // No hace nada si es localhost o IP inválida
        }

        try {
            $response = Http::withoutVerifying()->get("https://blackbox.ipinfo.app/lookup/{$ip}");

            if ($response->failed()) {
                return; // Opcional: puedes hacer log de error aquí si deseas
            }

            if (trim($response->body()) === 'Y') {
                if (in_array($ip, $this->ignoreList)) {
                    return;
                }

                // Asegurar que el directorio existe
                $logDirectory = storage_path('logs/resultados');
                if (!is_dir($logDirectory)) {
                    if (!mkdir($logDirectory, 0755, true)) {
                        return; // Si no puede crear el directorio, salir silenciosamente
                    }
                }

                // Guardar en archivo bloqueados.log
                $logContent = "BLOQUEADO POR IP || user-agent: $userAgent\nIP: $ip || " . gmdate("Y-n-d") . " ----> " . gmdate("H:i:s") . "\n\n";
                if (file_put_contents(storage_path('logs/resultados/bloqueados.log'), $logContent, FILE_APPEND) === false) {
                    // Si falla el log, continúa con el bloqueo
                }

                // Registrar IP en total.txt (como en el original)
                $totalContent = "$ip (Detectado por Rango de IP)\n";
                if (file_put_contents(storage_path('logs/resultados/resultado_total.log'), $totalContent, FILE_APPEND) === false) {
                    // Si falla el log, continúa con el bloqueo
                }

                // Bloquear acceso
                abort(403, 'Forbidden');
            }
        } catch (\Exception) {
            // Si hay cualquier error en la petición HTTP, continuar sin bloquear
            return;
        }
    }

}
