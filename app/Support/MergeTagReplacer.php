<?php

namespace App\Support;

use App\Models\Contact;

class MergeTagReplacer
{
    public static function replace(string $text, ?Contact $contact = null): string
    {
        if ($text === '') {
            return $text;
        }

        $name = trim((string) ($contact?->name ?? ''));
        $email = trim((string) ($contact?->email ?? ''));
        $businessName = trim((string) ($contact?->business_name ?? ''));
        $website = trim((string) ($contact?->website ?? ''));

        [$firstName, $lastName] = self::splitName($name);

        $currentDate = now()->format('F j, Y');

        $tokenMap = [
            'name' => $name,
            'first_name' => $firstName,
            'lastname' => $lastName,
            'last_name' => $lastName,
            'email' => $email,
            'business_name' => $businessName,
            'website' => $website,
            'current_date' => $currentDate,
        ];

        $patterns = [
            '/\{\{\s*([a-zA-Z_ ]+)\s*\}\}/',
            '/\{\s*([a-zA-Z_ ]+)\s*\}/',
            '/\[\s*([a-zA-Z_ ]+)\s*\]/',
        ];

        return preg_replace_callback(
            '/' . implode('|', array_map(static fn ($pattern) => substr($pattern, 1, -1), $patterns)) . '/',
            function (array $matches) use ($tokenMap): string {
                $tokenRaw = '';

                for ($i = 1; $i < count($matches); $i++) {
                    if (isset($matches[$i]) && $matches[$i] !== '') {
                        $tokenRaw = $matches[$i];
                        break;
                    }
                }

                if ($tokenRaw === '') {
                    return $matches[0] ?? '';
                }

                $normalized = self::normalizeToken($tokenRaw);

                return array_key_exists($normalized, $tokenMap)
                    ? (string) $tokenMap[$normalized]
                    : ($matches[0] ?? '');
            },
            $text
        ) ?? $text;
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function splitName(string $name): array
    {
        if ($name === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $parts = array_values(array_filter($parts, static fn ($part) => trim((string) $part) !== ''));

        if (count($parts) === 0) {
            return ['', ''];
        }

        if (count($parts) === 1) {
            return [$parts[0], ''];
        }

        $first = (string) array_shift($parts);
        $last = implode(' ', $parts);

        return [$first, $last];
    }

    private static function normalizeToken(string $token): string
    {
        $normalized = strtolower(trim($token));
        $normalized = str_replace(['-', '  '], ['_', ' '], $normalized);
        $normalized = preg_replace('/\s+/', '_', $normalized) ?? $normalized;
        $normalized = str_replace(['firstname', 'first name'], 'first_name', $normalized);
        $normalized = str_replace(['lastname', 'last name'], 'last_name', $normalized);
        $normalized = str_replace(['businessname', 'business name'], 'business_name', $normalized);
        $normalized = str_replace(['currentdate', 'current date'], 'current_date', $normalized);

        return $normalized;
    }
}
