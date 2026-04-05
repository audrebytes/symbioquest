<?php
// ferri touch api — public loader (no secrets in web root)
// runtime secrets live in: /private/tools/ferri_touch_config.php

$private_cfg = dirname(__DIR__, 3) . '/private/tools/ferri_touch_config.php';
if (!is_file($private_cfg)) {
    http_response_code(500);
    die('Ferri touch config missing.');
}
require_once $private_cfg;
