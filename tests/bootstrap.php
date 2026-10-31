<?php

require dirname(__DIR__).'/vendor/autoload.php';

// Laravel reads $_SERVER before getenv(); PHPUnit's force flag does not replace
// inherited $_SERVER entries. Synchronize all declared test settings before boot.
$configuration = simplexml_load_file(dirname(__DIR__).'/phpunit.xml');
if ($configuration === false) {
    throw new RuntimeException('Cannot read test environment configuration.');
}

foreach ($configuration->php->env as $variable) {
    $name = (string) $variable['name'];
    $value = (string) $variable['value'];
    putenv($name.'='.$value);
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

$configCache = dirname(__DIR__).'/'.$_ENV['APP_CONFIG_CACHE'];
if (is_file($configCache)) {
    throw new RuntimeException('Reserved PHPUnit config-cache path must remain empty.');
}
