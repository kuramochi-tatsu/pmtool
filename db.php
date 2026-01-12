<?php
require_once __DIR__ . '/config.php';

function db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;

  $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;

  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  // 文字化け対策（多くの環境で不要だが入れておくと安定）
  $pdo->exec("SET client_encoding TO 'UTF8'");

  return $pdo;
}
