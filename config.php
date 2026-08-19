<?php
// WARNING: This file was sanitized to remove sensitive credentials that were previously
// committed to this repository. Do NOT commit real credentials to source control.
//
// To configure your site on the server, copy config.sample.php to config.php or
// create config.php here with your real values. Keep config.php out of git and
// never push it to the repository.

define('DB_HOST', 'localhost');            // replace with your DB host
define('DB_NAME', 'your_database_name');   // replace with your DB name
define('DB_USER', 'your_database_user');   // replace with your DB user
define('DB_PASSWORD', 'your_database_password'); // replace with your DB password
define('APP_SECRET_KEY', 'replace_with_a_64_char_hex_string'); // generate with: openssl rand -hex 32
define('SITE_URL', 'https://yourdomain.example');
