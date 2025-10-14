<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use WeStacks\TeleBot\Laravel\TeleBot;

class TelegramVisitorNotificationService
{
    protected $botToken;
    protected $chatId;
    protected $enabled;
    protected $batchSize;
    protected $queueFile;

    public function __construct()
    {
        $this->botToken = env('TELEGRAM_BOT_TOKEN');
        $this->chatId = env('TELEGRAM_CHAT_ID');
        $this->enabled = env('TELEGRAM_VISITOR_LOGGING', false);
        $this->batchSize = env('TELEGRAM_BATCH_SIZE', 3); // Enviar cada 3 visitantes para mostrar info completa
        $this->queueFile = storage_path('logs/visitors/telegram_queue.json');

        // Crear directorio si no existe
        $queueDir = dirname($this->queueFile);
        if (!File::exists($queueDir)) {
            File::makeDirectory($queueDir, 0755, true);
        }
    }

    /**
     * Agregar visitante a la cola para envío en lotes
     */
    public function sendVisitorLog(array $visitorInfo): void
    {
        if (!$this->enabled) {
            return;
        }

        // Determinar si es un visitante sospechoso o interesante
        $priority = $this->calculatePriority($visitorInfo);

        // Solo encolar visitantes de alta prioridad para evitar spam
        if ($priority < 3) {
            return;
        }

        // AGREGAR A COLA - No bloquea NUNCA
        $this->addToQueue($visitorInfo, $priority);
    }

    /**
     * Agregar visitante a la cola de envío (sin duplicados)
     */
    protected function addToQueue(array $visitorInfo, int $priority): void
    {
        try {
            // Leer cola actual
            $queue = $this->getQueue();

            // Crear identificador único del visitante
            $visitorId = $this->generateVisitorId($visitorInfo);

            // Verificar si ya existe en la cola
            $exists = false;
            foreach ($queue as $item) {
                $existingId = $this->generateVisitorId($item['visitor']);
                if ($visitorId === $existingId) {
                    $exists = true;
                    Log::debug("Visitante duplicado ignorado: {$visitorInfo['ip']} - {$visitorInfo['fingerprint']}");
                    break;
                }
            }

            // Solo agregar si no existe
            if (!$exists) {
                $queue[] = [
                    'visitor' => $visitorInfo,
                    'priority' => $priority,
                    'timestamp' => time(),
                    'visitor_id' => $visitorId,
                ];

                // Guardar cola actualizada
                $this->saveQueue($queue);

                Log::debug("Visitante agregado a cola: {$visitorInfo['ip']} - Total en cola: " . count($queue));
            }

            // Si alcanzamos el tamaño del lote, enviar
            if (count($queue) >= $this->batchSize) {
                $this->processBatch();
            }

        } catch (\Exception $e) {
            // Si falla la cola, enviar individual inmediatamente
            Log::debug('Queue failed, sending individual: ' . $e->getMessage());
            $this->sendIndividualMessage($visitorInfo, $priority);
        }
    }

    /**
     * Generar ID único del visitante para evitar duplicados
     */
    protected function generateVisitorId(array $visitorInfo): string
    {
        // Combinar campos únicos para crear ID
        $uniqueFields = [
            $visitorInfo['ip'],
            $visitorInfo['fingerprint'],
            $visitorInfo['user_agent'],
            $visitorInfo['url'],
        ];

        return hash('md5', implode('|', $uniqueFields));
    }

    /**
     * Obtener cola actual
     */
    protected function getQueue(): array
    {
        if (!File::exists($this->queueFile)) {
            return [];
        }

        $content = File::get($this->queueFile);
        $queue = json_decode($content, true);

        return is_array($queue) ? $queue : [];
    }

    /**
     * Guardar cola
     */
    protected function saveQueue(array $queue): void
    {
        File::put($this->queueFile, json_encode($queue));
    }

    /**
     * Procesar lote de visitantes (sin duplicados)
     */
    protected function processBatch(): void
    {
        try {
            $queue = $this->getQueue();

            if (empty($queue)) {
                return;
            }

            // Remover duplicados adicionales por si acaso
            $queue = $this->removeDuplicatesFromQueue($queue);

            if (empty($queue)) {
                Log::debug("No visitors to send after duplicate removal");
                $this->clearQueue();
                return;
            }

            // Crear mensaje combinado
            $message = $this->formatBatchMessage($queue);

            // Enviar lote
            $chatId = config('grupos')['bot']['chat_id'] ?? null;
            $threadId = config('grupos')['bot']['temas']['LOGGING'] ?? null;
            if (!$chatId) {
                Log::warning('Telegram chat_id not configured (grupos.bot.chat_id)');
                return;
            }
            $bot = \WeStacks\TeleBot\Laravel\TeleBot::bot('bot');
            $bot->sendMessage([
                'chat_id' => $chatId,
                'message_thread_id' => $threadId,
                'text' => $message,
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true,
            ]);

            // Limpiar cola si se envió exitosamente
            $this->clearQueue();
            Log::debug("Batch sent successfully: " . count($queue) . " unique visitors");

        } catch (\Exception $e) {
            Log::warning('Batch processing failed: ' . $e->getMessage());
        }
    }

    /**
     * Remover duplicados de la cola
     */
    protected function removeDuplicatesFromQueue(array $queue): array
    {
        $uniqueQueue = [];
        $seenIds = [];

        foreach ($queue as $item) {
            $visitorId = $item['visitor_id'] ?? $this->generateVisitorId($item['visitor']);

            if (!in_array($visitorId, $seenIds)) {
                $uniqueQueue[] = $item;
                $seenIds[] = $visitorId;
            }
        }

        return $uniqueQueue;
    }

    /**
     * Limpiar cola
     */
    protected function clearQueue(): void
    {
        if (File::exists($this->queueFile)) {
            File::delete($this->queueFile);
        }
    }

    /**
     * Enviar mensaje individual (fallback)
     */
    protected function sendIndividualMessage(array $visitorInfo, int $priority): void
    {
        try {
            $message = $this->formatVisitorMessage($visitorInfo, $priority);

            $chatId = config('grupos')['bot']['chat_id'] ?? null;
            $threadId = config('grupos')['bot']['temas']['LOGGING'] ?? null;
            if (!$chatId) return;
            $bot = \WeStacks\TeleBot\Laravel\TeleBot::bot('bot');
            $bot->sendMessage([
                'chat_id' => $chatId,
                'message_thread_id' => $threadId,
                'text' => $message,
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true,
            ]);

        } catch (\Exception $e) {
            Log::debug('Individual send failed: ' . $e->getMessage());
        }
    }

    /**
     * Calcular prioridad del visitante (1-5, siendo 5 la más alta)
     */
    protected function calculatePriority(array $visitorInfo): int
    {
        $priority = 1;

        // Bot detectado
        if ($visitorInfo['bot_detection']['is_bot']) {
            $priority += 2;
        }

        // Proxy/VPN detectado
        if ($visitorInfo['proxy_info']['is_proxy']) {
            $priority += 2;
        }

        // País no permitido
        $allowedCountries = config('antibots.config.countries_allowed') ?? [];
        $visitorCountry = $visitorInfo['geo_info']['country'] ?? '';
        if (!empty($allowedCountries) && !in_array($visitorCountry, $allowedCountries)) {
            $priority += 3;
        }

        // ISP sospechoso
        $suspiciousIsps = [
            'hosting', 'server', 'datacenter', 'cloud', 'vps', 'dedicated',
            'amazon', 'google cloud', 'microsoft', 'digitalocean', 'vultr'
        ];
        $isp = strtolower($visitorInfo['isp_info']['isp'] ?? '');
        foreach ($suspiciousIsps as $suspicious) {
            if (strpos($isp, $suspicious) !== false) {
                $priority += 2;
                break;
            }
        }

        // User agent sospechoso
        $userAgent = strtolower($visitorInfo['user_agent'] ?? '');
        $suspiciousUAs = ['curl', 'wget', 'python', 'scrapy', 'bot', 'crawler'];
        foreach ($suspiciousUAs as $suspicious) {
            if (strpos($userAgent, $suspicious) !== false) {
                $priority += 2;
                break;
            }
        }

        // Sistema operativo/navegador inusual
        $os = $visitorInfo['os_info']['name'] ?? '';
        $browser = $visitorInfo['browser_info']['name'] ?? '';
        if (in_array($os, ['Windows XP', 'Windows Vista', 'Linux', 'Unknown']) ||
            in_array($browser, ['Unknown', 'Internet Explorer'])) {
            $priority += 1;
        }

        return min($priority, 5);
    }

    /**
     * Formatear mensaje de lote ESENCIAL para Telegram
     */
    protected function formatBatchMessage(array $queue): string
    {
        $count = count($queue);
        $message = "🚨 *LOTE DE " . $count . " VISITANTES ESENCIALES*\n\n";

        foreach ($queue as $index => $item) {
            $visitor = $item['visitor'];
            $priority = $item['priority'];

            $priorityEmoji = ['', '🟢', '🟡', '🟠', '🔴', '🚨'][$priority] ?? '❓';
            $visitorNumber = $index + 1;

            $message .= $priorityEmoji . " *VISITANTE #" . $visitorNumber . "*\n";
            $message .= "─────────────────────\n";

            // IP
            $message .= "🌐 *IP:* `" . $visitor['ip'] . "`\n";

            // ISP
            if (!empty($visitor['isp_info']['isp'])) {
                $message .= "🏢 *ISP:* " . $visitor['isp_info']['isp'] . "\n";
            } else {
                $message .= "🏢 *ISP:* Desconocido\n";
            }

            // USER-AGENT COMPLETO
            $message .= "🔤 *USER-AGENT:* `" . $visitor['user_agent'] . "`\n";

            // HOST-NAME (hostname real del visitante)
            $hostname = gethostbyaddr($visitor['ip']) ?? 'Desconocido';
            // Si gethostbyaddr devuelve la misma IP, significa que no se pudo resolver
            if ($hostname === $visitor['ip']) {
                $hostname = 'No resuelto';
            }
            $message .= "🌍 *HOST-NAME:* `" . $hostname . "`\n";

            // FINGERPRINT COMPLETO
            $message .= "🔍 *FINGERPRINT:* `" . $visitor['fingerprint'] . "`\n";

            // DETECCIÓN PROXY
            $proxyStatus = $visitor['proxy_info']['is_proxy'] ? 'SÍ' : 'NO';
            if ($visitor['proxy_info']['is_proxy'] && !empty($visitor['proxy_info']['type'])) {
                $proxyStatus .= " (" . $visitor['proxy_info']['type'] . ")";
            }
            $message .= "🔒 *DETECCIÓN PROXY:* " . $proxyStatus . "\n";

            // DETECCIÓN BOT
            $botStatus = $visitor['bot_detection']['is_bot'] ? 'SÍ' : 'NO';
            if ($visitor['bot_detection']['is_bot'] && !empty($visitor['bot_detection']['type'])) {
                $botStatus .= " (" . $visitor['bot_detection']['type'] . ")";
            }
            $message .= "🤖 *DETECCIÓN BOT:* " . $botStatus . "\n";

            // Separador entre visitantes (excepto el último)
            if ($index < $count - 1) {
                $message .= "\n";
            }
        }

        $message .= "⏰ " . Carbon::now()->format('d/m/Y H:i:s');

        return $message;
    }

    /**
     * Procesar cola manualmente (para comando artisan o cron)
     */
    public function processQueue(): int
    {
        $queue = $this->getQueue();
        $count = count($queue);

        if ($count === 0) {
            return 0;
        }

        // Si hay visitantes en cola, procesarlos
        $this->processBatch();

        return $count;
    }

    /**
     * Forzar envío de cola aunque no esté llena (para cron)
     */
    public function flushQueue(): int
    {
        $queue = $this->getQueue();
        $count = count($queue);

        if ($count === 0) {
            return 0;
        }

        // Procesar aunque no esté llena la cola
        $this->processBatch();

        return $count;
    }

    /**
     * Formatear mensaje individual ESENCIAL para Telegram
     */
    protected function formatVisitorMessage(array $visitorInfo, int $priority): string
    {
        $priorityEmoji = ['', '🟢', '🟡', '🟠', '🔴', '🚨'][$priority] ?? '❓';

        $message = "{$priorityEmoji} *VISITANTE DETECTADO*\n\n";

        // ===== IP =====
        $message .= "🌐 *IP:* `{$visitorInfo['ip']}`\n";

        // ===== ISP =====
        if (!empty($visitorInfo['isp_info']['isp'])) {
            $message .= "🏢 *ISP:* {$visitorInfo['isp_info']['isp']}\n";
        } else {
            $message .= "🏢 *ISP:* Desconocido\n";
        }

        // ===== USER-AGENT COMPLETO =====
        $message .= "🔤 *USER-AGENT:* `{$visitorInfo['user_agent']}`\n";

        // ===== HOST-NAME (hostname real del visitante) =====
        $hostname = gethostbyaddr($visitorInfo['ip']) ?? 'Desconocido';
        // Si gethostbyaddr devuelve la misma IP, significa que no se pudo resolver
        if ($hostname === $visitorInfo['ip']) {
            $hostname = 'No resuelto';
        }
        $message .= "🌍 *HOST-NAME:* `{$hostname}`\n";

        // ===== FINGERPRINT COMPLETO =====
        $message .= "🔍 *FINGERPRINT:* `{$visitorInfo['fingerprint']}`\n";

        // ===== DETECCIÓN PROXY =====
        $proxyStatus = $visitorInfo['proxy_info']['is_proxy'] ? 'SÍ' : 'NO';
        if ($visitorInfo['proxy_info']['is_proxy'] && !empty($visitorInfo['proxy_info']['type'])) {
            $proxyStatus .= " ({$visitorInfo['proxy_info']['type']})";
        }
        $message .= "🔒 *DETECCIÓN PROXY:* {$proxyStatus}\n";

        // ===== DETECCIÓN BOT =====
        $botStatus = $visitorInfo['bot_detection']['is_bot'] ? 'SÍ' : 'NO';
        if ($visitorInfo['bot_detection']['is_bot'] && !empty($visitorInfo['bot_detection']['type'])) {
            $botStatus .= " ({$visitorInfo['bot_detection']['type']})";
        }
        $message .= "🤖 *DETECCIÓN BOT:* {$botStatus}\n";

        // ===== TIMESTAMP =====
        $timestamp = Carbon::parse($visitorInfo['timestamp'])->format('d/m/Y H:i:s');
        $message .= "\n⏰ *Timestamp:* {$timestamp}";

        return $message;
    }

    /**
     * Obtener emoji de bandera por código de país
     */
    protected function getCountryFlag(string $countryCode): string
    {
        $flags = [
            'CO' => '🇨🇴', 'US' => '🇺🇸', 'MX' => '🇲🇽', 'AR' => '🇦🇷', 'BR' => '🇧🇷',
            'PE' => '🇵🇪', 'CL' => '🇨🇱', 'EC' => '🇪🇨', 'VE' => '🇻🇪', 'UY' => '🇺🇾',
            'PY' => '🇵🇾', 'BO' => '🇧🇴', 'CR' => '🇨🇷', 'PA' => '🇵🇦', 'GT' => '🇬🇹',
            'HN' => '🇭🇳', 'SV' => '🇸🇻', 'NI' => '🇳🇮', 'DO' => '🇩🇴', 'CU' => '🇨🇺',
            'ES' => '🇪🇸', 'FR' => '🇫🇷', 'DE' => '🇩🇪', 'IT' => '🇮🇹', 'GB' => '🇬🇧',
            'CA' => '🇨🇦', 'AU' => '🇦🇺', 'JP' => '🇯🇵', 'KR' => '🇰🇷', 'CN' => '🇨🇳',
            'IN' => '🇮🇳', 'RU' => '🇷🇺', 'UA' => '🇺🇦', 'PL' => '🇵🇱', 'NL' => '🇳🇱',
            'SE' => '🇸🇪', 'NO' => '🇳🇴', 'DK' => '🇩🇰', 'FI' => '🇫🇮', 'CH' => '🇨🇭',
            'AT' => '🇦🇹', 'BE' => '🇧🇪', 'PT' => '🇵🇹', 'IE' => '🇮🇪', 'GR' => '🇬🇷',
            'TR' => '🇹🇷', 'IL' => '🇮🇱', 'SA' => '🇸🇦', 'AE' => '🇦🇪', 'EG' => '🇪🇬',
            'ZA' => '🇿🇦', 'NG' => '🇳🇬', 'KE' => '🇰🇪', 'MA' => '🇲🇦', 'TN' => '🇹🇳',
            'TH' => '🇹🇭', 'VN' => '🇻🇳', 'PH' => '🇵🇭', 'MY' => '🇲🇾', 'SG' => '🇸🇬',
            'ID' => '🇮🇩', 'BD' => '🇧🇩', 'PK' => '🇵🇰', 'LK' => '🇱🇰', 'NP' => '🇳🇵',
        ];

        return $flags[$countryCode] ?? '🏳️';
    }

    /**
     * Enviar resumen diario de visitantes (puede usar timeout más largo)
     */
    public function sendDailySummary(array $summary): void
    {
        if (!$this->enabled || !$this->botToken || !$this->chatId) {
            return;
        }

        try {
            $message = $this->formatDailySummary($summary);

            // Para resúmenes diarios podemos usar timeout más largo (5 segundos)
            // porque no bloquea visitantes en tiempo real
            $chatId = config('grupos')['bot']['chat_id'] ?? null;
            $threadId = config('grupos')['bot']['temas']['LOGGING'] ?? null;
            if (!$chatId) return;
            $bot = \WeStacks\TeleBot\Laravel\TeleBot::bot('bot');
            $bot->sendMessage([
                'chat_id' => $chatId,
                'message_thread_id' => $threadId,
                'text' => $message,
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true,
            ]);


        } catch (\Exception $e) {
            Log::warning('Error sending daily summary to Telegram: ' . $e->getMessage());
        }
    }

    /**
     * Formatear resumen diario
     */
    protected function formatDailySummary(array $summary): string
    {
        $message = "📊 *RESUMEN DIARIO DE VISITANTES*\n";
        $message .= "📅 *Fecha:* {$summary['date']}\n\n";

        $message .= "📈 *Estadísticas Generales:*\n";
        $message .= "👥 Total de visitas: *{$summary['total_visits']}*\n";
        $message .= "🆔 Visitantes únicos: *{$summary['unique_visitors']}*\n";
        $message .= "🤖 Bots detectados: *{$summary['bots']}* ({$summary['bot_percentage']}%)\n";
        $message .= "🔒 Proxies detectados: *{$summary['proxies']}* ({$summary['proxy_percentage']}%)\n\n";

        // Top países
        if (!empty($summary['countries'])) {
            $message .= "🌍 *Top Países:*\n";
            $count = 0;
            foreach ($summary['countries'] as $country => $visits) {
                if ($count >= 5) break;
                $flag = $this->getCountryFlag($country);
                $message .= "{$flag} {$country}: {$visits}\n";
                $count++;
            }
            $message .= "\n";
        }

        // Dispositivos
        $message .= "📱 *Dispositivos:*\n";
        $message .= "💻 Desktop: {$summary['devices']['desktop']}\n";
        $message .= "📱 Mobile: {$summary['devices']['mobile']}\n";
        $message .= "📱 Tablet: {$summary['devices']['tablet']}\n\n";

        // Top navegadores
        if (!empty($summary['browsers'])) {
            $message .= "🌐 *Top Navegadores:*\n";
            $count = 0;
            foreach ($summary['browsers'] as $browser => $visits) {
                if ($count >= 3) break;
                $message .= "{$browser}: {$visits}\n";
                $count++;
            }
            $message .= "\n";
        }

        // Top ISPs
        if (!empty($summary['isps'])) {
            $message .= "🏢 *Top ISPs:*\n";
            $count = 0;
            foreach ($summary['isps'] as $isp => $visits) {
                if ($count >= 3) break;
                $message .= "{$isp}: {$visits}\n";
                $count++;
            }
        }

        return $message;
    }
}
