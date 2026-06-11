<?php
// Copy this file, rename it to config.php
// and fill in your actual values.
// config.php is in .gitignore and will never be committed.

define('DB_HOST', 'localhost');
define('DB_NAME', 'heritagelink_db');
define('DB_USER', 'YOUR_DATABASE_USERNAME');
define('DB_PASS', 'YOUR_DATABASE_PASSWORD');
define('SITE_NAME', 'HeritageLink');
define('SITE_URL',  'http://localhost/heritagelink');
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024);
define('SESSION_NAME', 'heritagelink_session');