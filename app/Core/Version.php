<?php

declare(strict_types=1);

namespace App\Core;

/**
 * ALT-044: версия сайта из git (число коммитов, короткий хеш, время обновления).
 * Кеш — storage/version.json (в .gitignore), пересобирается только когда сменился HEAD,
 * поэтому git на каждый запрос не запускается: читаются два маленьких файла.
 */
final class Version
{
    /** @return array{count:int,short:string,full:string,subject:string,updated:int}|null */
    public static function info(): ?array
    {
        static $memo = false;
        if ($memo !== false) {
            return $memo;
        }
        try {
            $memo = self::load();
        } catch (\Throwable $e) {
            $memo = null;
        }
        return $memo;
    }

    private static function load(): ?array
    {
        $root = dirname(__DIR__, 2);
        [$hash, $stamp] = self::readHead($root . '/.git');
        $cacheFile = $root . '/storage/version.json';

        $cached = null;
        if (is_file($cacheFile)) {
            $raw = json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($raw) && isset($raw['full'])) {
                $cached = $raw;
            }
        }
        if ($hash === null) {
            // .git недоступен — показываем то, что есть в кеше
            return $cached ?: null;
        }
        if ($cached && $cached['full'] === $hash && (int)($cached['count'] ?? 0) > 0) {
            return $cached;
        }

        $info = [
            'count'   => 0,
            'short'   => substr($hash, 0, 7),
            'full'    => $hash,
            'subject' => '',
            'updated' => $stamp ?: time(),
        ];
        $count = self::git($root, 'rev-list --count HEAD');
        if ($count !== null && ctype_digit($count)) {
            $info['count'] = (int)$count;
        }
        $subject = self::git($root, 'log -1 --format=%s');
        if ($subject !== null) {
            $info['subject'] = mb_substr($subject, 0, 200);
        }
        // без счётчика (git недоступен) кеш не пишем, чтобы повторить попытку позже
        if ($info['count'] > 0) {
            @file_put_contents($cacheFile, json_encode($info, JSON_UNESCAPED_UNICODE), LOCK_EX);
        }
        return $info['count'] > 0 || $info['short'] !== '' ? $info : null;
    }

    /** @return array{0:?string,1:int} хеш HEAD и время его обновления (mtime файла ссылки) */
    private static function readHead(string $gitDir): array
    {
        $headFile = $gitDir . '/HEAD';
        $head = is_file($headFile) ? trim((string)@file_get_contents($headFile)) : '';
        if ($head === '') {
            return [null, 0];
        }
        if (preg_match('/^[0-9a-f]{40}$/', $head)) {
            return [$head, (int)@filemtime($headFile)];
        }
        if (strpos($head, 'ref: ') !== 0) {
            return [null, 0];
        }
        $ref = trim(substr($head, 5));
        if (!preg_match('#^refs/[A-Za-z0-9._/-]+$#', $ref) || strpos($ref, '..') !== false) {
            return [null, 0];
        }
        $refFile = $gitDir . '/' . $ref;
        if (is_file($refFile)) {
            $h = trim((string)@file_get_contents($refFile));
            if (preg_match('/^[0-9a-f]{40}$/', $h)) {
                return [$h, (int)@filemtime($refFile)];
            }
        }
        $packed = $gitDir . '/packed-refs';
        if (is_file($packed)) {
            foreach (explode("\n", (string)@file_get_contents($packed)) as $line) {
                if (preg_match('/^([0-9a-f]{40}) ' . preg_quote($ref, '/') . '$/', trim($line), $m)) {
                    return [$m[1], (int)@filemtime($packed)];
                }
            }
        }
        return [null, 0];
    }

    private static function git(string $root, string $args): ?string
    {
        if (!function_exists('exec')) {
            return null;
        }
        $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
        if (in_array('exec', $disabled, true)) {
            return null;
        }
        $dir = escapeshellarg($root);
        $safe = escapeshellarg('safe.directory=' . $root);
        $out = [];
        $code = 1;
        @exec('git -c ' . $safe . ' -C ' . $dir . ' ' . $args . ' 2>&1', $out, $code);
        if ($code !== 0 || !$out) {
            return null;
        }
        return trim((string)$out[0]);
    }
}
