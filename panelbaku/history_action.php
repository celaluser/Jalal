<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

register_shutdown_function(function () {
    $error = error_get_last();
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if ($error !== null && in_array($error['type'], $fatalTypes, true)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'ok'    => false,
            'error' => 'Server xətası: ' . $error['message']
                . ' (' . basename($error['file']) . ':' . $error['line'] . ')',
        ]);
    }
});

try {
    require __DIR__ . '/config.php';
    require __DIR__ . '/HistoryStore.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Yalnız POST sorğusu qəbul olunur.']);
        exit;
    }

    $input  = json_decode((string)file_get_contents('php://input'), true);
    $action = (string)($input['action'] ?? '');

    $store = new HistoryStore(HISTORY_FILE, HISTORY_LIMIT);

    if ($action === 'delete') {
        $id = (string)($input['id'] ?? '');
        if ($id === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID göndərilməyib.']);
            exit;
        }
        $store->delete($id);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'clear') {
        $store->clear();
        echo json_encode(['ok' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Naməlum əməliyyat.']);

} catch (Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo json_encode([
        'ok'    => false,
        'error' => 'PHP xətası: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')',
    ]);
}
