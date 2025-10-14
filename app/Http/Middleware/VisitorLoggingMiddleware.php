<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\VisitorLoggingService;
use App\Services\TelegramVisitorNotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class VisitorLoggingMiddleware
{
    protected $telegramService;

    public function __construct(TelegramVisitorNotificationService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Capturar información del visitante antes de procesar la request
        $this->logVisitor($request);

        return $next($request);
    }

    /**
     * Registrar información completa del visitante
     */
    protected function logVisitor(Request $request): void
    {
        try {
            $loggingService = new VisitorLoggingService($request);
            $visitorInfo = $loggingService->captureVisitorInfo();

            // Guardar en archivo de log local
            $this->saveToLocalLog($visitorInfo);

            // Enviar a Telegram si está configurado
            if (env('TELEGRAM_VISITOR_LOGGING', false)) {
                $this->telegramService->sendVisitorLog($visitorInfo);
            }

            // Log en Laravel para debugging
            Log::channel('visitor')->info('Visitor logged', [
                'ip' => $visitorInfo['ip'],
                'user_agent' => $visitorInfo['user_agent'],
                'url' => $visitorInfo['url'],
                'country' => $visitorInfo['geo_info']['country'] ?? 'Unknown',
                'is_bot' => $visitorInfo['bot_detection']['is_bot'],
                'is_proxy' => $visitorInfo['proxy_info']['is_proxy'],
            ]);

        } catch (\Exception $e) {
            Log::error('Error logging visitor: ' . $e->getMessage(), [
                'ip' => $request->ip(),
                'url' => $request->fullUrl(),
                'user_agent' => $request->userAgent(),
            ]);
        }
    }

    /**
     * Guardar información en archivo de log local
     */
    protected function saveToLocalLog(array $visitorInfo): void
    {
        $logDir = storage_path('logs/visitors');

        // Crear directorio si no existe
        if (!File::exists($logDir)) {
            File::makeDirectory($logDir, 0755, true);
        }

        // Archivo de log diario
        $logFile = $logDir . '/visitors_' . Carbon::now()->format('Y-m-d') . '.log';

        // Formato de log estructurado
        $logEntry = [
            'timestamp' => $visitorInfo['timestamp'],
            'ip' => $visitorInfo['ip'],
            'country' => $visitorInfo['geo_info']['country'] ?? 'Unknown',
            'city' => $visitorInfo['geo_info']['city'] ?? 'Unknown',
            'isp' => $visitorInfo['isp_info']['isp'] ?? 'Unknown',
            'user_agent' => $visitorInfo['user_agent'],
            'url' => $visitorInfo['url'],
            'method' => $visitorInfo['method'],
            'referer' => $visitorInfo['referer'],
            'device_type' => $visitorInfo['device_info']['device_type'],
            'browser' => $visitorInfo['browser_info']['name'] . ' ' . $visitorInfo['browser_info']['version'],
            'os' => $visitorInfo['os_info']['name'] . ' ' . $visitorInfo['os_info']['version'],
            'is_bot' => $visitorInfo['bot_detection']['is_bot'],
            'bot_type' => $visitorInfo['bot_detection']['type'],
            'is_proxy' => $visitorInfo['proxy_info']['is_proxy'],
            'fingerprint' => $visitorInfo['fingerprint'],
            'language' => $visitorInfo['language'],
        ];

        // Guardar como JSON para fácil procesamiento
        File::append($logFile, json_encode($logEntry) . "\n");

        // También guardar en formato legible
        $readableLogFile = $logDir . '/visitors_readable_' . Carbon::now()->format('Y-m-d') . '.log';
        $readableEntry = sprintf(
            "[%s] %s | %s (%s, %s) | %s | %s | %s | %s | Bot: %s | Proxy: %s | %s\n",
            $visitorInfo['timestamp'],
            $visitorInfo['ip'],
            $visitorInfo['geo_info']['country'] ?? 'Unknown',
            $visitorInfo['geo_info']['city'] ?? 'Unknown',
            $visitorInfo['isp_info']['isp'] ?? 'Unknown',
            $visitorInfo['device_info']['device_type'],
            $visitorInfo['browser_info']['name'],
            $visitorInfo['os_info']['name'],
            $visitorInfo['url'],
            $visitorInfo['bot_detection']['is_bot'] ? 'YES (' . $visitorInfo['bot_detection']['type'] . ')' : 'NO',
            $visitorInfo['proxy_info']['is_proxy'] ? 'YES' : 'NO',
            $visitorInfo['user_agent']
        );

        File::append($readableLogFile, $readableEntry);

        // Archivo de resumen diario
        $this->updateDailySummary($visitorInfo);
    }

    /**
     * Actualizar resumen diario de visitantes
     */
    protected function updateDailySummary(array $visitorInfo): void
    {
        $summaryFile = storage_path('logs/visitors/daily_summary_' . Carbon::now()->format('Y-m-d') . '.json');

        $summary = [];
        if (File::exists($summaryFile)) {
            $summary = json_decode(File::get($summaryFile), true) ?? [];
        }

        // Inicializar contadores si no existen
        if (!isset($summary['total_visits'])) {
            $summary = [
                'date' => Carbon::now()->format('Y-m-d'),
                'total_visits' => 0,
                'unique_ips' => [],
                'countries' => [],
                'devices' => ['mobile' => 0, 'tablet' => 0, 'desktop' => 0],
                'browsers' => [],
                'operating_systems' => [],
                'bots' => 0,
                'proxies' => 0,
                'isps' => [],
                'top_pages' => [],
                'referrers' => [],
            ];
        }

        // Actualizar contadores
        $summary['total_visits']++;

        // IPs únicas
        if (!in_array($visitorInfo['ip'], $summary['unique_ips'])) {
            $summary['unique_ips'][] = $visitorInfo['ip'];
        }

        // Países
        $country = $visitorInfo['geo_info']['country'] ?? 'Unknown';
        $summary['countries'][$country] = ($summary['countries'][$country] ?? 0) + 1;

        // Dispositivos
        $deviceType = $visitorInfo['device_info']['device_type'];
        if (isset($summary['devices'][$deviceType])) {
            $summary['devices'][$deviceType]++;
        }

        // Navegadores
        $browser = $visitorInfo['browser_info']['name'];
        $summary['browsers'][$browser] = ($summary['browsers'][$browser] ?? 0) + 1;

        // Sistemas operativos
        $os = $visitorInfo['os_info']['name'];
        $summary['operating_systems'][$os] = ($summary['operating_systems'][$os] ?? 0) + 1;

        // Bots
        if ($visitorInfo['bot_detection']['is_bot']) {
            $summary['bots']++;
        }

        // Proxies
        if ($visitorInfo['proxy_info']['is_proxy']) {
            $summary['proxies']++;
        }

        // ISPs
        $isp = $visitorInfo['isp_info']['isp'] ?? 'Unknown';
        $summary['isps'][$isp] = ($summary['isps'][$isp] ?? 0) + 1;

        // Páginas más visitadas
        $url = $visitorInfo['url'];
        $summary['top_pages'][$url] = ($summary['top_pages'][$url] ?? 0) + 1;

        // Referrers
        if (!empty($visitorInfo['referer'])) {
            $summary['referrers'][$visitorInfo['referer']] = ($summary['referrers'][$visitorInfo['referer']] ?? 0) + 1;
        }

        // Mantener solo los top 10 para evitar archivos muy grandes
        arsort($summary['countries']);
        $summary['countries'] = array_slice($summary['countries'], 0, 10, true);

        arsort($summary['browsers']);
        $summary['browsers'] = array_slice($summary['browsers'], 0, 10, true);

        arsort($summary['operating_systems']);
        $summary['operating_systems'] = array_slice($summary['operating_systems'], 0, 10, true);

        arsort($summary['isps']);
        $summary['isps'] = array_slice($summary['isps'], 0, 10, true);

        arsort($summary['top_pages']);
        $summary['top_pages'] = array_slice($summary['top_pages'], 0, 10, true);

        arsort($summary['referrers']);
        $summary['referrers'] = array_slice($summary['referrers'], 0, 10, true);

        // Calcular estadísticas adicionales
        $summary['unique_visitors'] = count($summary['unique_ips']);
        $summary['bot_percentage'] = round(($summary['bots'] / $summary['total_visits']) * 100, 2);
        $summary['proxy_percentage'] = round(($summary['proxies'] / $summary['total_visits']) * 100, 2);

        File::put($summaryFile, json_encode($summary, JSON_PRETTY_PRINT));
    }
}
