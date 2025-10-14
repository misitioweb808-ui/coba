<?php

namespace App\Services\Antibots\Rules;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class BlockerService
{
    public function run()
    {
        $ip = $this->getIpAddress();
        $userAgent = request()->header('User-Agent') ?? '';

        $blockedIps = $this->getBlockedList('blocked_ip.log');
        $blockedUAs = $this->getBlockedList('blocked_ua.log');

        // Validar que el User-Agent no esté vacío antes de verificar
        $isIpBlocked = in_array($ip, $blockedIps);
        $isUserAgentBlocked = !empty($userAgent) && in_array($userAgent, $blockedUAs);

        if ($isIpBlocked || $isUserAgentBlocked) {
            // Log del bloqueo para auditoría
            Log::info('BlockerService: Access blocked', [
                'ip' => $ip,
                'user_agent' => $userAgent,
                'reason' => $isIpBlocked ? 'blocked_ip' : 'blocked_ua'
            ]);

            abort(403, 'Forbidden');
        }
    }

    public function getBlockedList(string $filename): array
    {
        try {
            $path = storage_path("logs/blacklist/{$filename}");

            // Verificar que el directorio existe
            $directory = dirname($path);
            if (!File::isDirectory($directory)) {
                Log::warning("BlockerService: Blacklist directory does not exist: {$directory}");
                return [];
            }

            if (!File::exists($path)) {
                return []; // Si el archivo no existe, no hay bloqueos.
            }

            // Verificar que el archivo es legible
            if (!File::isReadable($path)) {
                Log::error("BlockerService: Blacklist file is not readable: {$path}");
                return [];
            }

            $lines = File::get($path);

            // Validar que el contenido no esté vacío
            if (empty($lines)) {
                return [];
            }

            // Filtrar líneas vacías y hacer trim
            $blockedItems = array_filter(
                array_map('trim', explode("\n", $lines)),
                function($item) {
                    return !empty($item) && strlen($item) > 0;
                }
            );

            return array_values($blockedItems); // Reindexar el array

        } catch (\Exception $e) {
            Log::error("BlockerService: Error reading blacklist file {$filename}", [
                'error' => $e->getMessage(),
                'file' => $filename
            ]);
            return [];
        }
    }

    public function getIpAddress(): string
    {
        return request()->server('HTTP_CF_CONNECTING_IP')
            ?? request()->server('HTTP_CLIENT_IP')
            ?? request()->server('HTTP_X_FORWARDED_FOR')
            ?? request()->server('HTTP_X_FORWARDED')
            ?? request()->server('HTTP_FORWARDED_FOR')
            ?? request()->server('HTTP_FORWARDED')
            ?? request()->ip();
    }
}
