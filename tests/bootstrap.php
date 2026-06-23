<?php
// Bootstrap for Convoca tests using the live Docker WordPress
// No separate test WP installation needed.

// Load WordPress
require_once '/var/www/html/wp-load.php';

// Load plugin
require_once dirname(__DIR__) . '/convoca-core.php';
