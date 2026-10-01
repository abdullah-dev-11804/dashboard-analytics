<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics;

use block_dashboardanalytics\repository\employee_status_repository;

defined('MOODLE_INTERNAL') || die();

/**
 * Captures permanent company departure events from Moodle and IOMAD.
 */
class observer {
    public static function company_user_unassigned(\core\event\base $event): void {
        self::record_iomad_event($event, 'companychange');
    }

    public static function company_user_deleted(\core\event\base $event): void {
        self::record_iomad_event($event, 'deleted');
    }

    public static function company_user_suspended(\core\event\base $event): void {
        self::record_iomad_event($event, 'suspended');
    }

    public static function user_deleted(\core\event\user_deleted $event): void {
        global $DB;

        $userid = (int)$event->objectid;
        if ($userid <= 0 || !$DB->get_manager()->table_exists(new \xmldb_table('company_users'))) {
            return;
        }

        $repository = new employee_status_repository();
        foreach ($DB->get_records('company_users', ['userid' => $userid], '', 'id, companyid') as $membership) {
            $companyid = (int)$membership->companyid;
            if ($companyid <= 0) {
                continue;
            }
            $repository->record_event(
                $userid,
                $companyid,
                'deleted',
                (int)$event->userid,
                (int)$event->timecreated,
                get_class($event) . ':' . (int)$event->id . ':' . $companyid,
                ''
            );
        }
    }

    private static function record_iomad_event(\core\event\base $event, string $action): void {
        $other = is_array($event->other) ? $event->other : [];
        $companyid = isset($other['companyid']) ? (int)$other['companyid'] : 0;
        if ($companyid <= 0 && !empty($other['oldcompany'])) {
            $companyid = self::companyid_from_old_company($other['oldcompany']);
        }
        if ($companyid <= 0 && $action !== 'deleted') {
            $companyid = (int)$event->objectid;
        }

        $userid = (int)$event->relateduserid;
        if ($userid <= 0 && !empty($other['userid'])) {
            $userid = (int)$other['userid'];
        }
        if ($userid <= 0 && $action === 'deleted') {
            $userid = (int)$event->objectid;
        }

        if ($userid <= 0 || $companyid <= 0) {
            return;
        }

        $actorid = (int)$event->userid;
        (new employee_status_repository())->record_event(
            $userid,
            $companyid,
            $action,
            $actorid,
            (int)$event->timecreated,
            get_class($event) . ':' . (int)$event->id . ':' . $companyid . ':' . $userid,
            (string)($other['companyname'] ?? ''),
            json_encode($other)
        );
    }

    private static function companyid_from_old_company($value): int {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }
        if (!is_array($value)) {
            return 0;
        }
        foreach (['companyid', 'id'] as $key) {
            if (isset($value[$key]) && is_numeric($value[$key])) {
                return (int)$value[$key];
            }
        }
        return 0;
    }
}
