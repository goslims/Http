<?php
/**
 * Plugin Name: SLiMS Http Test Plugin
 * Plugin URI: -
 * Description: -
 * Version: 1.0.0
 * Author: Drajat Hasan
 * Author URI: https://t.me/drajathasan
 */
use SLiMS\Plugins;
$plugins = Plugins::getInstance();

require __DIR__ . '/../vendor/autoload.php';

$plugins->registerMenu('opac', 'api', __DIR__ . '/pages/routes.php');