<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics;

defined('MOODLE_INTERNAL') || die();

class name_formatter {

    public static function last_first_from_parts(string $firstname, string $lastname, string $fallback = ''): string {
        $name = trim(trim($lastname) . ' ' . trim($firstname));
        if ($name !== '') {
            return $name;
        }

        return $fallback !== '' ? $fallback : get_string('hiddenuser');
    }

    public static function last_first(\stdClass $user, string $fallback = ''): string {
        return self::last_first_from_parts(
            (string)($user->firstname ?? ''),
            (string)($user->lastname ?? ''),
            $fallback
        );
    }
}
