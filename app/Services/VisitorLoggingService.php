<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class VisitorLoggingService
{
    protected $request;
    protected $geoApiEndpoints = [
        'http://extreme-ip-lookup.com/json/',
        'http://ip-api.com/json/',
        'https://ipapi.co/',
        'https://freegeoip.app/json/'
    ];

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Capturar toda la información del visitante
     */
    public function captureVisitorInfo(): array
    {
        $ip = $this->getIpAddress();
        $userAgent = $this->request->userAgent();

        return [
            'timestamp' => Carbon::now()->toISOString(),
            'ip' => $ip,
            'user_agent' => $userAgent,
            'url' => $this->request->fullUrl(),
            'method' => $this->request->method(),
            'referer' => $this->request->header('referer'),
            'headers' => $this->getAllHeaders(),
            'geo_info' => $this->getGeolocationInfo($ip),
            'isp_info' => $this->getIspInfo($ip),
            'device_info' => $this->getDeviceInfo($userAgent),
            'browser_info' => $this->getBrowserInfo($userAgent),
            'os_info' => $this->getOsInfo($userAgent),
            'fingerprint' => $this->generateFingerprint(),
            'proxy_info' => $this->detectProxy($ip),
            'bot_detection' => $this->detectBot($userAgent, $ip),
            'session_info' => $this->getSessionInfo(),
            'language' => $this->request->header('accept-language'),
            'encoding' => $this->request->header('accept-encoding'),
            'connection_type' => $this->request->header('connection'),
            'dnt' => $this->request->header('dnt'), // Do Not Track
            'sec_fetch_site' => $this->request->header('sec-fetch-site'),
            'sec_fetch_mode' => $this->request->header('sec-fetch-mode'),
            'sec_fetch_user' => $this->request->header('sec-fetch-user'),
            'sec_fetch_dest' => $this->request->header('sec-fetch-dest'),
        ];
    }

    /**
     * Obtener IP real del visitante
     */
    protected function getIpAddress(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load Balancer/Proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'HTTP_X_REAL_IP',           // Nginx proxy
            'REMOTE_ADDR'               // Standard
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $this->request->ip() ?? '0.0.0.0';
    }

    /**
     * Obtener todos los headers HTTP
     */
    protected function getAllHeaders(): array
    {
        $headers = [];
        foreach ($this->request->headers->all() as $key => $value) {
            $headers[$key] = is_array($value) ? implode(', ', $value) : $value;
        }
        return $headers;
    }

    /**
     * Obtener información de geolocalización
     */
    protected function getGeolocationInfo(string $ip): array
    {
        $cacheKey = "geo_info_{$ip}";

        return Cache::remember($cacheKey, 3600, function () use ($ip) {
            foreach ($this->geoApiEndpoints as $endpoint) {
                try {
                    $response = Http::timeout(5)->get($endpoint . $ip);

                    if ($response->successful()) {
                        $data = $response->json();

                        return [
                            'country' => $data['country'] ?? $data['countryCode'] ?? null,
                            'country_name' => $data['countryName'] ?? $data['country'] ?? null,
                            'region' => $data['region'] ?? $data['regionName'] ?? null,
                            'city' => $data['city'] ?? null,
                            'zip' => $data['zip'] ?? $data['postal'] ?? null,
                            'lat' => $data['lat'] ?? $data['latitude'] ?? null,
                            'lon' => $data['lon'] ?? $data['longitude'] ?? null,
                            'timezone' => $data['timezone'] ?? null,
                            'source' => $endpoint
                        ];
                    }
                } catch (\Exception $e) {
                    Log::warning("Error getting geo info from {$endpoint}: " . $e->getMessage());
                    continue;
                }
            }

            return ['error' => 'No geo data available'];
        });
    }

    /**
     * Obtener información del ISP (múltiples fuentes)
     */
    protected function getIspInfo(string $ip): array
    {
        $cacheKey = "isp_info_{$ip}";

        return Cache::remember($cacheKey, 3600, function () use ($ip) {
            // Múltiples APIs para ISP
            $ispApis = [
                "http://ip-api.com/json/{$ip}?fields=org,as,asname,isp",
                "http://extreme-ip-lookup.com/json/{$ip}",
                "https://ipapi.co/{$ip}/json/",
            ];

            foreach ($ispApis as $api) {
                try {
                    $response = Http::timeout(3)->get($api);

                    if ($response->successful()) {
                        $data = $response->json();

                        // Extraer ISP según la API
                        $isp = null;
                        $as = null;
                        $asname = null;

                        if (strpos($api, 'ip-api.com') !== false) {
                            $isp = $data['org'] ?? $data['isp'] ?? null;
                            $as = $data['as'] ?? null;
                            $asname = $data['asname'] ?? null;
                        } elseif (strpos($api, 'extreme-ip-lookup.com') !== false) {
                            $isp = $data['org'] ?? $data['isp'] ?? null;
                            $as = $data['as'] ?? null;
                            $asname = $data['asname'] ?? null;
                        } elseif (strpos($api, 'ipapi.co') !== false) {
                            $isp = $data['org'] ?? null;
                            $as = $data['asn'] ?? null;
                            $asname = $data['asn_name'] ?? null;
                        }

                        // Si encontramos ISP, devolver resultado
                        if (!empty($isp)) {
                            return [
                                'isp' => $isp,
                                'as' => $as,
                                'asname' => $asname,
                                'source' => $api
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    Log::debug("ISP API failed {$api}: " . $e->getMessage());
                    continue;
                }
            }

            // Si todas las APIs fallan, intentar con whois básico
            try {
                $hostname = gethostbyaddr($ip);
                if ($hostname !== $ip) {
                    // Extraer ISP del hostname
                    $parts = explode('.', $hostname);
                    if (count($parts) >= 2) {
                        $possibleIsp = $parts[count($parts) - 2] . '.' . $parts[count($parts) - 1];
                        return [
                            'isp' => $possibleIsp,
                            'as' => null,
                            'asname' => null,
                            'source' => 'hostname'
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::debug("Hostname ISP extraction failed: " . $e->getMessage());
            }

            return [
                'isp' => 'Desconocido',
                'as' => null,
                'asname' => null,
                'error' => 'No ISP data available'
            ];
        });
    }

    /**
     * Obtener información del dispositivo
     */
    protected function getDeviceInfo(string $userAgent): array
    {
        $isMobile = $this->isMobile($userAgent);
        $isTablet = $this->isTablet($userAgent);

        return [
            'is_mobile' => $isMobile,
            'is_tablet' => $isTablet,
            'is_desktop' => !$isMobile && !$isTablet,
            'device_type' => $isMobile ? 'mobile' : ($isTablet ? 'tablet' : 'desktop'),
            'screen_resolution' => $this->request->header('sec-ch-viewport-width') . 'x' . $this->request->header('sec-ch-viewport-height'),
        ];
    }

    /**
     * Detectar si es móvil
     */
    protected function isMobile(string $userAgent): bool
    {
        return preg_match('/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $userAgent);
    }

    /**
     * Detectar si es tablet
     */
    protected function isTablet(string $userAgent): bool
    {
        return preg_match('/iPad|Android(?!.*Mobile)|Tablet/i', $userAgent);
    }

    /**
     * Obtener información del navegador
     */
    protected function getBrowserInfo(string $userAgent): array
    {
        $browser = 'Unknown';
        $version = 'Unknown';

        $browsers = [
            'Chrome' => '/Chrome\/([0-9.]+)/',
            'Firefox' => '/Firefox\/([0-9.]+)/',
            'Safari' => '/Safari\/([0-9.]+)/',
            'Edge' => '/Edge\/([0-9.]+)/',
            'Opera' => '/Opera\/([0-9.]+)/',
            'Internet Explorer' => '/MSIE ([0-9.]+)/',
        ];

        foreach ($browsers as $name => $pattern) {
            if (preg_match($pattern, $userAgent, $matches)) {
                $browser = $name;
                $version = $matches[1] ?? 'Unknown';
                break;
            }
        }

        return [
            'name' => $browser,
            'version' => $version,
            'full_string' => $userAgent
        ];
    }

    /**
     * Obtener información del sistema operativo
     */
    protected function getOsInfo(string $userAgent): array
    {
        $os = 'Unknown';
        $version = 'Unknown';

        $systems = [
            'Windows 11' => '/Windows NT 10.0.*rv:.*\) like Gecko/',
            'Windows 10' => '/Windows NT 10.0/',
            'Windows 8.1' => '/Windows NT 6.3/',
            'Windows 8' => '/Windows NT 6.2/',
            'Windows 7' => '/Windows NT 6.1/',
            'Windows Vista' => '/Windows NT 6.0/',
            'Windows XP' => '/Windows NT 5.1/',
            'Mac OS X' => '/Mac OS X ([0-9._]+)/',
            'macOS' => '/macOS ([0-9._]+)/',
            'Linux' => '/Linux/',
            'Ubuntu' => '/Ubuntu/',
            'Android' => '/Android ([0-9.]+)/',
            'iOS' => '/OS ([0-9_]+) like Mac OS X/',
        ];

        foreach ($systems as $name => $pattern) {
            if (preg_match($pattern, $userAgent, $matches)) {
                $os = $name;
                $version = $matches[1] ?? 'Unknown';
                break;
            }
        }

        return [
            'name' => $os,
            'version' => $version
        ];
    }

    /**
     * Generar fingerprint único
     */
    protected function generateFingerprint(): string
    {
        $components = [
            $this->request->userAgent(),
            $this->request->header('accept-language'),
            $this->request->header('accept-encoding'),
            $this->request->header('accept'),
            $this->getIpAddress(),
            $this->request->header('sec-ch-ua'),
            $this->request->header('sec-ch-ua-platform'),
        ];

        return hash('sha256', implode('|', array_filter($components)));
    }

    /**
     * Detectar proxy/VPN
     */
    protected function detectProxy(string $ip): array
    {
        $proxyHeaders = [
            'HTTP_VIA',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED',
            'HTTP_CLIENT_IP',
            'HTTP_FORWARDED_FOR_IP',
            'VIA',
            'X_FORWARDED_FOR',
            'FORWARDED_FOR',
            'X_FORWARDED',
            'FORWARDED',
            'CLIENT_IP',
            'FORWARDED_FOR_IP',
            'HTTP_PROXY_CONNECTION'
        ];

        $isProxy = false;
        $proxyType = 'none';
        $detectedHeaders = [];

        foreach ($proxyHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $isProxy = true;
                $detectedHeaders[] = $header;
            }
        }

        if ($isProxy) {
            $proxyType = 'detected';
        }

        return [
            'is_proxy' => $isProxy,
            'type' => $proxyType,
            'detected_headers' => $detectedHeaders
        ];
    }

    /**
     * Detectar bots
     */
    protected function detectBot(string $userAgent, string $ip): array
    {
        $botPatterns = [
            'Googlebot', 'Bingbot', 'Slurp', 'DuckDuckBot', 'Baiduspider',
            'YandexBot', 'facebookexternalhit', 'Twitterbot', 'LinkedInBot',
            'WhatsApp', 'Telegram', 'crawler', 'spider', 'bot', 'scraper'
        ];

        $isBot = false;
        $botType = 'none';

        foreach ($botPatterns as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                $isBot = true;
                $botType = $pattern;
                break;
            }
        }

        return [
            'is_bot' => $isBot,
            'type' => $botType,
            'confidence' => $isBot ? 'high' : 'low'
        ];
    }

    /**
     * Obtener información de sesión
     */
    protected function getSessionInfo(): array
    {
        return [
            'session_id' => session()->getId(),
            'csrf_token' => csrf_token(),
            'has_session' => session()->isStarted(),
        ];
    }
}
