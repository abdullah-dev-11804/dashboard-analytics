<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

defined('MOODLE_INTERNAL') || die();

class completion_repository {

    public function count_completed_courses(array $filters): int {
        global $DB;

        $employee = new employee_repository();
        $analytics = new course_analytics_repository();
        $userfilter = $employee->user_filter_sql($filters, 'u', 'completion');
        $where = [$userfilter['sql'], 'cc.timecompleted IS NOT NULL', $analytics->eligibility_where_sql('c', 'cfcompletion', 'cdcompletion')];
        $params = $userfilter['params'];

        if (!empty($filters['courseids'])) {
            [$insql, $inparams] = $DB->get_in_or_equal($filters['courseids'], SQL_PARAMS_NAMED, 'completioncourse');
            $where[] = "cc.course {$insql}";
            $params += $inparams;
        }

        $sql = "SELECT COUNT(1)
                  FROM {course_completions} cc
                  JOIN {course} c ON c.id = cc.course
                  {$analytics->eligibility_join_sql('c', 'cfcompletion', 'cdcompletion')}
                  JOIN {user} u ON u.id = cc.userid
                 WHERE " . implode(' AND ', $where);

        return (int)$DB->count_records_sql($sql, $params);
    }
}
