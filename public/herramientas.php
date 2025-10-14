<?php
/**
 * Página de descarga de herramientas de soporte remoto
 * Simula la descarga de una herramienta de soporte
 */

// Headers para descarga
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="remote-utilities-host-7.6.2.0.msi"');
header('Content-Length: ' . filesize(__FILE__));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// En un entorno real, aquí se serviría el archivo real
// Por ahora, creamos un archivo de ejemplo

$contenido = "Este es un archivo de ejemplo para herramientas de soporte remoto.\n";
$contenido .= "En un entorno de producción, aquí se descargaría el archivo real.\n";
$contenido .= "Fecha de generación: " . date('Y-m-d H:i:s') . "\n";
$contenido .= "Versión: 7.6.2.0\n";

echo $contenido;
exit;
?>
