<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function h($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
  header("Location: {$url}");
  exit;
}

function csrf_token(): string {
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
  }
  return $_SESSION['csrf'];
}

function verify_csrf(): void {
  $token = $_POST['csrf'] ?? '';
  if (!$token || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
    http_response_code(400);
    echo "Bad Request (CSRF)";
    exit;
  }
}

function q(string $key, $default=null) {
  return $_GET[$key] ?? $default;
}
function p(string $key, $default=null) {
  return $_POST[$key] ?? $default;
}
