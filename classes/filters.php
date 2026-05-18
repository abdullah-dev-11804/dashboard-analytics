<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics;

defined('MOODLE_INTERNAL') || die();

class filters {

    public static function from_json(?string $json): array {
        $decoded = json_decode((string)$json, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        return [
            'companyids' => self::int_list($decoded['companyids'] ?? $decoded['companies'] ?? []),
            'courseids' => self::int_list($decoded['courseids'] ?? $decoded['courses'] ?? []),
            'departments' => self::text_list($decoded['departments'] ?? $decoded['departmentids'] ?? []),
            'locations' => self::text_list($decoded['locations'] ?? $decoded['locationids'] ?? []),
            'positions' => self::text_list($decoded['positions'] ?? $decoded['positionids'] ?? []),
            'status' => self::status($decoded['status'] ?? ''),
            'search' => trim(clean_param((string)($decoded['search'] ?? ''), PARAM_TEXT)),
        ];
    }

    private static function int_list($value): array {
        if (!is_array($value)) {
            $value = $value === '' || $value === null ? [] : [$value];
        }

        $items = [];
        foreach ($value as $item) {
            $item = (int)$item;
            if ($item > 0) {
                $items[] = $item;
            }
        }

        return array_values(array_unique($items));
    }

    private static function text_list($value): array {
        if (!is_array($value)) {
            $value = $value === '' || $value === null ? [] : [$value];
        }

        $items = [];
        foreach ($value as $item) {
            $item = trim(clean_param((string)$item, PARAM_TEXT));
            if ($item !== '') {
                $items[] = $item;
            }
        }

        return array_values(array_unique($items));
    }

    private static function status($value): string {
        $value = clean_param((string)$value, PARAM_ALPHANUMEXT);
        return in_array($value, ['expired', 'expiring', 'active', 'nodocument'], true) ? $value : '';
    }
}

