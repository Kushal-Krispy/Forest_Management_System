<?php
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function requireApiLogin(): void
{
    if (!isLoggedIn()) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }
    validateSessionUser();
}
