<?php

$config = [
    'timezone_api' => [
        'key' => '',
        'format' => 'json',
        'sleep' => 1, // бесплатный акк timezonedb.com обрабатывает не больше 1 запроса в секунду
    ],
    'mysql' => [
        'host' => '127.0.0.1',
        'dbname' => '',
        'user' => '',
        'password' => '',
    ],
    'future_interval' => 3, // месяцы, интервал на который заглянем в будущее
];

return $config;