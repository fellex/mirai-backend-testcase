<?php

$config = [
    'timezone_api' => [
        'key' => '',
        'format' => 'json',
        'sleep' => 1, // ���������� ��� timezonedb.com ������������ �� ������ 1 ������� � �������
    ],
    'mysql' => [
        'host' => '127.0.0.1',
        'dbname' => '',
        'user' => '',
        'password' => '',
    ],
    'future_interval' => 3, // ������, �������� �� ������� �������� � �������
];

return $config;