<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

defined('MOODLE_INTERNAL') || die();

class report_repository {

    public function company_options(): array {
        global $DB;

        if (!$this->table_exists('company')) {
            return [];
        }

        $records = $DB->get_records('company', null, 'name ASC', 'id, name', 0, 1000);
        $options = [];

        foreach ($records as $record) {
            $options[] = [
                'value' => (string)$record->id,
                'label' => format_string($record->name),
            ];
        }

        return $options;
    }

    public function company_name(int $companyid): string {
        global $DB;

        if ($companyid <= 0 || !$this->table_exists('company')) {
            return '';
        }

        return (string)$DB->get_field('company', 'name', ['id' => $companyid], IGNORE_MISSING);
    }

    public function act_service_rows(int $companyid, int $month, int $year): array {
        global $DB;

        if ($companyid <= 0 || $month < 1 || $month > 12 || $year < 2000) {
            return [];
        }

        if (!$this->table_exists('company_users')) {
            return [];
        }

        $start = make_timestamp($year, $month, 1, 0, 0, 0);
        $end = strtotime('+1 month', $start) - 1;
        $employee = new employee_repository();
        $where = [
            'c.visible = 1',
            'c.id <> :siteid',
        ];
        $params = [
            'companyid' => $companyid,
            'starttime' => $start,
            'endtime' => $end,
            'siteid' => SITEID,
        ];
        $employee->append_sental_student_only_filter($where, $params, 'u', 'reportsact');

        $sql = "SELECT c.id AS courseid,
                    c.fullname AS coursename,
                    COUNT(DISTINCT cc.userid) AS lmscount
                FROM {course} c
                JOIN {enrol} e
                    ON e.courseid = c.id
                AND e.status = 0
                JOIN {user_enrolments} ue
                    ON ue.enrolid = e.id
                AND ue.status = 0
                JOIN {user} u
                    ON u.id = ue.userid
                JOIN {company_users} cu
                    ON cu.userid = ue.userid
                AND cu.companyid = :companyid
            LEFT JOIN {course_completions} cc
                    ON cc.userid = ue.userid
                AND cc.course = c.id
                AND cc.timecompleted IS NOT NULL
                AND cc.timecompleted BETWEEN :starttime AND :endtime
                WHERE " . implode(' AND ', $where) . "
            GROUP BY c.id, c.fullname
            ORDER BY c.fullname ASC";

        $records = $DB->get_records_sql($sql, $params);
        $rows = [];
        $index = 1;

        foreach ($records as $record) {
            $lmscount = (int)$record->lmscount;

            /*
             * Keep courses even when count is zero because the TechSpec says rows come from
             * visible courses employees are enrolled in.
             */
            $rows[] = [
                'number' => $index,
                'courseid' => (int)$record->courseid,
                'coursename' => format_string($record->coursename),
                'unit' => get_string('reportsact:unitservice', 'block_dashboardanalytics'),
                'lmscount' => $lmscount,
                'actqty' => $lmscount,
            ];

            $index++;
        }

        return $rows;
    }

    private function table_exists(string $tablename): bool {
        global $DB;

        $manager = $DB->get_manager();
        return $manager->table_exists(new \xmldb_table($tablename));
    }
}
