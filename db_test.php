<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/debug-error.log');

require_once __DIR__ . '/db.php';

try {
  $pdo = db();
  echo "DB CONNECT OK<br>";
  echo "version: " . htmlspecialchars($pdo->query("SELECT version()")->fetchColumn(), ENT_QUOTES, 'UTF-8');
} catch (Throwable $e) {
  http_response_code(500);
  echo "DB CONNECT FAILED<br>";
  echo nl2br(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
