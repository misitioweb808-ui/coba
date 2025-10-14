<?php

namespace App\Services\Antibots\Rules\Guard;

class BlockFp
{
    public array $whitelistedIps = [
        '91.108.5.7',
        '205.210.31.19',
        '91.108.5.49',
        '40.113.118.83'
    ];

    public function run()
    {
        $ip = request()->ip();
        $userAgent = request()->userAgent();

        if (!$ip) {
            return;
        }

        // Verificar IPs permitidas
        if (in_array($ip, $this->whitelistedIps)) {
            return;
        }

        $os = $this->getOS($userAgent);
        $browser = $this->getBrowser($userAgent);

        // Lógica de bloqueo por fingerprint
        if (($os == "Windows Server 2003/XP x64" && $browser == "Firefox") ||
            ($os == "Windows 7" && $browser == "Firefox") ||
            ($os == "Windows XP" && in_array($browser, ["Firefox", "Internet Explorer", "Chrome"])) ||
            ($os == "Windows Vista" && $browser == "Internet Explorer") ||
            in_array($os, ["Windows Vista", "Ubuntu", "Chrome OS", "BlackBerry", "Linux"]) ||
            $browser == "Unknown Browser" ||
            $browser == "Internet Explorer" ||
            $os == "Windows 2000" ||
            $os == "Unknown OS Platform") {

            $this->logDetection($ip, $userAgent);
            abort(403, 'Forbidden');
        }
    }

    public function getOS(string $userAgent): string
    {
        $osPlatform = "Unknown OS Platform";
        $osArray = [
            '/windows nt 10/i'      => 'Windows 10',
            '/windows nt 6.3/i'     => 'Windows 8.1',
            '/windows nt 6.2/i'     => 'Windows 8',
            '/windows nt 6.1/i'     => 'Windows 7',
            '/windows nt 6.0/i'     => 'Windows Vista',
            '/windows nt 5.2/i'     => 'Windows Server 2003/XP x64',
            '/windows nt 5.1/i'     => 'Windows XP',
            '/windows xp/i'         => 'Windows XP',
            '/windows nt 5.0/i'     => 'Windows 2000',
            '/windows me/i'         => 'Windows ME',
            '/win98/i'             => 'Windows 98',
            '/win95/i'             => 'Windows 95',
            '/win16/i'             => 'Windows 3.11',
            '/macintosh|mac os x/i' => 'Mac OS X',
            '/mac_powerpc/i'       => 'Mac OS 9',
            '/linux/i'             => 'Linux',
            '/ubuntu/i'           => 'Ubuntu',
            '/iphone/i'           => 'iPhone',
            '/ipod/i'             => 'iPod',
            '/ipad/i'             => 'iPad',
            '/android/i'           => 'Android',
            '/blackberry/i'       => 'BlackBerry',
            '/webos/i'           => 'Mobile'
        ];

        foreach ($osArray as $regex => $value) {
            if (preg_match($regex, $userAgent)) {
                $osPlatform = $value;
                break;
            }
        }

        return $osPlatform;
    }

    public function getBrowser(string $userAgent): string
    {
        $browser = "Unknown Browser";
        $browserArray = [
            '/msie/i'      => 'Internet Explorer',
            '/firefox/i'   => 'Firefox',
            '/safari/i'    => 'Safari',
            '/chrome/i'    => 'Chrome',
            '/opera/i'     => 'Opera',
            '/netscape/i'  => 'Netscape',
            '/maxthon/i'   => 'Maxthon',
            '/konqueror/i' => 'Konqueror',
            '/mobile/i'    => 'Handheld Browser'
        ];

        foreach ($browserArray as $regex => $value) {
            if (preg_match($regex, $userAgent)) {
                $browser = $value;
                break;
            }
        }

        return $browser;
    }

    public function logDetection(string $ip, string $userAgent): void
    {
        // Asegurar que el directorio existe
        $logDirectory = storage_path('logs/resultados');
        if (!is_dir($logDirectory)) {
            mkdir($logDirectory, 0755, true);
        }

        // Guardar en archivo bloqueados.log
        file_put_contents(
            storage_path('logs/resultados/bloqueados.log'),
            "BLOQUEADO POR FP|| user-agent: $userAgent\nIP: $ip || " . gmdate("Y-n-d") . " ----> " . gmdate("H:i:s") . "\n\n",
            FILE_APPEND
        );

        // Registrar IP en resultado_total.log
        file_put_contents(
            storage_path('logs/resultados/resultado_total.log'),
            "$ip (Detectado FingerPrint)\n",
            FILE_APPEND
        );
    }
}
