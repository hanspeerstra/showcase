<?php

namespace App\Http;

class RequestUtils
{
    /**
     * @return string[]
     */
    public static function readCommaSeperatedList(string $list): array
    {
        $items = [];
        foreach (explode(',', $list) as $item) {
            if (trim($item) === '') {
                continue;
            }

            $items[] = trim($item);
        }

        return $items;
    }
}
