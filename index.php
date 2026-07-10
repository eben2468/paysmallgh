<?php
declare(strict_types=1);

/**
 * Root entry point for shared hosting whose document root is the PROJECT ROOT
 * (not /public). It simply hands off to the real front controller in /public,
 * which defines BASE_PATH relative to itself, so everything resolves correctly.
 *
 * Requires the sibling root .htaccess to (a) route requests here, (b) map
 * /assets and /uploads into /public, and (c) deny access to .env, app/, etc.
 *
 * If your host lets you point the domain's document root at the /public folder,
 * prefer that — it's cleaner and keeps source files off the web entirely. This
 * file is the fallback for hosts where you can't change the document root.
 */
require __DIR__ . '/public/index.php';
