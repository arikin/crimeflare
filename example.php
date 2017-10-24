<?php
require_once __DIR__ . '/vendor/autoload.php';

use Arikin\Crimeflare;
ini_set('memory_limit', '1G');

$options = array(
    'settings_file' => __DIR__ . '/crimeflare.json'
);
$crime = new Crimeflare();
// Depends on your MySQL settings.
//$crime->setBindingLimit(30000);
$crime->update();
