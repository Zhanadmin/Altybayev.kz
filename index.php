<?php

declare(strict_types=1);

/**
 * Точка входа для серверов, у которых DocumentRoot указывает на корень проекта (/httpdocs),
 * а не на /httpdocs/public.
 */
require_once __DIR__ . '/public/index.php';
