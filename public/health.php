<?php
declare(strict_types=1);
use App\Database\Connection;
require dirname(__DIR__).'/bootstrap/bootstrap.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
try { $ok=(int)Connection::get()->query('SELECT 1')->fetchColumn()===1; } catch (Throwable $e) { error_log($e->getMessage()); $ok=false; }
http_response_code($ok?200:503);
echo json_encode(['status'=>$ok?'ok':'degraded','database'=>$ok?'ok':'unavailable','php'=>PHP_VERSION], JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);