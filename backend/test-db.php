
<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script must be run from the command line.');
}

require_once __DIR__ . '/db.php';

fwrite(STDOUT, "Database connection succeeded.\n");
