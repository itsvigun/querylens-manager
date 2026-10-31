<?php

// Static assets are explicitly allowlisted; all other paths enter Symfony.
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/style.css') { return false; }
require __DIR__.'/index.php';
