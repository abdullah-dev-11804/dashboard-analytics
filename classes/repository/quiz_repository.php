<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

defined('MOODLE_INTERNAL') || die();

class quiz_repository {

    public function first_attempt_pass_rate_by_course_items(array $filters, float $threshold = 60.0, int $limit = 12): array {
        global $DB;

        $employee = new employee_repository();
        $userfilter = $employee->user_filter_sql($filters, 'u', 'qfirstattempt');
        $where = [
            $userfilter['sql'],
            'qa.preview = 0',
            'qa.state = :qfirstattemptfinished',
            'qa.attempt = 1',
            'qa.timefinish > 0',
            'gi.gradepass > 0',
        ];
        $params = $userfilter['params'] + [
            'qfirstattemptfinished' => 'finished',
        ];

        if (!empty($filters['courseids'])) {
            [$insql, $inparams] = $DB->get_in_or_equal($filters['courseids'], SQL_PARAMS_NAMED, 'qfirstattemptcourse');
            $where[] = "c.id {$insql}";
            $params += $inparams;
        }

        $this->append_date_filter($where, $params, 'qa.timefinish', $filters, 'qfirstattemptdate');

        $finalgrade = "CASE
                         WHEN qu.sumgrades > 0 AND qu.grade > 0
                              THEN (COALESCE(qa.sumgrades, 0) * qu.grade / qu.sumgrades)
                         ELSE COALESCE(qa.sumgrades, 0)
                       END";

        $sql = "SELECT c.id AS courseid,
                       c.fullname AS coursename,
                       COUNT(qa.id) AS totalfirstattempts,
                       SUM(CASE WHEN {$finalgrade} >= gi.gradepass THEN 1 ELSE 0 END) AS passedfirstattempts
                  FROM {quiz_attempts} qa
                  JOIN {quiz} qu ON qu.id = qa.quiz
                  JOIN {course} c ON c.id = qu.course
                  JOIN {user} u ON u.id = qa.userid
                  JOIN {grade_items} gi ON gi.courseid = c.id
                       AND gi.itemtype = :qfirstattemptitemtype
                       AND gi.itemmodule = :qfirstattemptitemmodule
                       AND gi.iteminstance = qu.id
                       AND gi.itemnumber = 0
                 WHERE " . implode(' AND ', $where) . "
              GROUP BY c.id, c.fullname
                HAVING COUNT(qa.id) > 0
              ORDER BY SUM(CASE WHEN {$finalgrade} >= gi.gradepass THEN 1 ELSE 0 END) * 1.0 / COUNT(qa.id) ASC,
                       COUNT(qa.id) DESC,
                       c.fullname ASC";

        $params += [
            'qfirstattemptitemtype' => 'mod',
            'qfirstattemptitemmodule' => 'quiz',
        ];

        $records = $DB->get_records_sql($sql, $params, 0, $limit);
        $items = [];
        foreach ($records as $record) {
            $total = max(0, (int)$record->totalfirstattempts);
            $passed = max(0, (int)$record->passedfirstattempts);
            if ($total <= 0) {
                continue;
            }

            $rate = round(($passed / $total) * 100, 1);
            $items[] = [
                'label' => format_string((string)$record->coursename),
                'url' => (new \moodle_url('/course/view.php', ['id' => (int)$record->courseid]))->out(false),
                'value' => $this->format_percent($rate),
                'percent' => $rate,
                'status' => $this->pass_rate_status($rate, $threshold),
                'meta' => get_string('quality:passrate:meta', 'block_dashboardanalytics', (object)[
                    'passed' => $passed,
                    'total' => $total,
                    'threshold' => $this->format_number($threshold),
                ]),
            ];
        }

        return $items;
    }

    private function pass_rate_status(float $rate, float $threshold): string {
        if ($rate < $threshold) {
            return 'danger';
        }

        if ($rate < min(100.0, $threshold + 10.0)) {
            return 'warning';
        }

        return 'ok';
    }

    private function append_date_filter(array &$where, array &$params, string $field, array $filters, string $prefix): void {
        $range = (string)($filters['daterange'] ?? 'last12months');
        $now = time();
        $start = null;
        $end = null;

        if ($range === 'customrange') {
            if (!empty($filters['customstart'])) {
                $starttime = strtotime((string)$filters['customstart'] . ' 00:00:00');
                if ($starttime !== false) {
                    $start = $starttime;
                }
            }
            if (!empty($filters['customend'])) {
                $endtime = strtotime((string)$filters['customend'] . ' 23:59:59');
                if ($endtime !== false) {
                    $end = $endtime;
                }
            }
        } else if ($range !== 'alltime') {
            $days = [
                'day' => 1,
                'week' => 7,
                'month' => 30,
                'last30days' => 30,
                'last90days' => 90,
                '6months' => 183,
                'last6months' => 183,
                'year' => 365,
                'last12months' => 365,
            ][$range] ?? 365;
            $start = $now - ($days * DAYSECS);
        }

        if ($start !== null) {
            $where[] = "{$field} >= :{$prefix}start";
            $params[$prefix . 'start'] = $start;
        }
        if ($end !== null) {
            $where[] = "{$field} <= :{$prefix}end";
            $params[$prefix . 'end'] = $end;
        }
    }

    private function format_percent(float $value): string {
        return $this->format_number($value) . '%';
    }

    private function format_number(float $value): string {
        $rounded = round($value, 1);
        if (abs($rounded - round($rounded)) < 0.05) {
            return (string)(int)round($rounded);
        }
        return format_float($rounded, 1);
    }
}
