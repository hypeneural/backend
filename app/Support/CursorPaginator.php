<?php

namespace App\Support;

class CursorPaginator
{
    /**
     * Encode cursor data to base64 JSON
     */
    public static function encodeCursor(array $data): string
    {
        return base64_encode(json_encode($data));
    }

    /**
     * Decode cursor from base64 JSON
     */
    public static function decodeCursor(string $cursor): ?array
    {
        try {
            $decoded = base64_decode($cursor, true);
            if ($decoded === false) {
                return null;
            }

            $data = json_decode($decoded, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            return $data;
        } catch (\Exception $e) {
            return null;
        }
    }
}
