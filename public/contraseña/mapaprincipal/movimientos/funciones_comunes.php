<?php
declare(strict_types=1);

// (Debe contener sanitize_input, redirect_to, format_datetime_user como en la respuesta anterior)
// Si no las tienes, avísame y las incluyo de nuevo.

function sanitize_input(?string $data): string {
    if ($data === null) return '';
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function redirect_to(string $url): void {
    header("Location: {$url}");
    exit();
}

function format_datetime_user(?string $datetime_str, string $format = 'd/m/Y H:i'): string {
    if (empty($datetime_str) || $datetime_str === '0000-00-00 00:00:00' || $datetime_str === 'N/A') {
        return '---';
    }
    try {
        $date = new DateTime($datetime_str);
        return $date->format($format);
    } catch (Exception $e) {
        return $datetime_str; // Devuelve original si no se puede formatear
    }
}
?>