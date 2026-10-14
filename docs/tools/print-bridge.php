#!/usr/bin/env php
<?php

/**
 * Printer bridge: collects queued tickets from your QR menu site and sends them to a thermal (ESC/POS) printer.
 *
 * Run it on any computer in the restaurant that can reach the printer, and leave it running:
 *
 *   php print-bridge.php --url="https://your-site.com/print/<secret-address>" --printer=tcp://192.168.1.50:9100
 *   php print-bridge.php --url="..." --printer=/dev/usb/lp0            (Linux USB printer)
 *   php print-bridge.php --url="..." --printer='\\localhost\ReceiptPrinter'   (Windows shared printer)
 *
 * The address is in Settings → Ordering → Thermal printer. Needs PHP 8 with the curl extension (or allow_url_fopen).
 * Stop it with Ctrl+C. It asks for work every 2 seconds and tells the site when a ticket printed.
 */
$opts = getopt('', ['url:', 'printer:', 'interval::']);

if (empty($opts['url']) || empty($opts['printer'])) {
    fwrite(STDERR, "Usage: php print-bridge.php --url=<address> --printer=<tcp://host:9100 | /dev/usb/lp0 | \\\\host\\printer>\n");
    exit(1);
}

$base = rtrim($opts['url'], '/');
$interval = max(1, (int) ($opts['interval'] ?? 2));
echo date('H:i:s')." Bridge started. Waiting for tickets...\n";

function request(string $method, string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$code, $body === false ? '' : $body];
}

function send(string $printer, string $bytes): bool
{
    if (str_starts_with($printer, 'tcp://')) {
        $socket = @stream_socket_client($printer, $errno, $error, 5);

        if (! $socket) {
            fwrite(STDERR, "Cannot reach the printer: {$error}\n");

            return false;
        }

        $ok = fwrite($socket, $bytes) !== false;
        fclose($socket);

        return $ok;
    }

    return @file_put_contents($printer, $bytes) !== false;
}

while (true) {
    [$code, $body] = request('GET', $base.'/next');

    if ($code === 200 && ($job = json_decode($body, true)) && isset($job['id'], $job['payload'])) {
        $ok = send($opts['printer'], (string) base64_decode($job['payload']));
        request('POST', $base.'/'.$job['id'].'/done'.($ok ? '' : '?ok=0'));
        echo date('H:i:s').' Ticket #'.$job['id'].' ('.$job['kind'].') '.($ok ? 'printed' : 'FAILED')."\n";

        continue; // maybe more waiting
    }

    if ($code !== 204 && $code !== 200) {
        fwrite(STDERR, date('H:i:s')." The site answered {$code}. Check the address.\n");
    }

    sleep($interval);
}
