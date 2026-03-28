<?php

declare(strict_types=1);

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function post(string $key, ?string $default = null): ?string
{
    if (!isset($_POST[$key])) {
        return $default;
    }

    $value = trim((string) $_POST[$key]);

    return $value === '' ? $default : $value;
}

function nullableInt(?string $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    return (int) $value;
}

function boolToInt(?string $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    return in_array(strtolower($value), ['1', 'igen', 'yes', 'true'], true) ? 1 : 0;
}

function nowIso(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c');
}
