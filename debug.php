<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// エラーをファイルにも吐く（同フォルダに debug-error.log ができる）
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/debug-error.log');

echo "PHP OK<br>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "pdo: " . (extension_loaded('pdo') ? 'yes' : 'no') . "<br>";
echo "pdo_pgsql: " . (extension_loaded('pdo_pgsql') ? 'yes' : 'no') . "<br>";
