<?php

declare(strict_types=1);

namespace app\businesses;

final class TagNameBusiness
{
    public function normalizeList(array $names): ?array
    {
        if (count($names) > 20) {
            return null;
        }

        $normalized = [];
        foreach ($names as $name) {
            if (!is_string($name)) {
                return null;
            }
            $name = preg_replace('/\s+/u', ' ', trim($name));
            if (!is_string($name) || $name === '' || mb_strlen($name, 'UTF-8') > 40) {
                return null;
            }
            $key = mb_strtolower($name, 'UTF-8');
            if (!isset($normalized[$key])) {
                $normalized[$key] = ['name' => $name, 'normalized_name' => $key];
            }
        }

        return array_values($normalized);
    }
}
