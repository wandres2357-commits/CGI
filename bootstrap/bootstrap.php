<?php
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
spl_autoload_register(static function(string $class): void { $prefix='App\\'; if(!str_starts_with($class,$prefix)) return; $file=BASE_PATH.'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php'; if(is_file($file)) require $file; });
App\Config\Env::load(BASE_PATH.'/.env');
date_default_timezone_set(App\Config\Env::get('APP_TIMEZONE','America/Bogota'));
ini_set('display_errors', App\Config\Env::bool('APP_DEBUG')?'1':'0');
error_reporting(E_ALL);