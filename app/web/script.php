<?php

$config = require __DIR__ . '/../load.php';
$config = require __DIR__ . '/../config.php';

$db = db\connection($config['db']);

if ($_GET['load'] ?? null) {
    $data = stat\all($db);

    header('Content-Type: application/json; charset=UTF-8');
    header(implode(' ', [$_SERVER['SERVER_PROTOCOL'], 200, 'OK']));
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
} else {
    require __DIR__ . '/../view/index.php';
}

