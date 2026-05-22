<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

defined('MOODLE_INTERNAL') || die();

class employee_repository {

    public function count_active_users(array $filters): int {
        global $DB;

        $filter = $this->user_filter_sql($filters, 'u', 'staff');
        $sql = "SELECT COUNT(1)
                  FROM {user} u
                 WHERE {$filter['sql']}";

        return (int)$DB->count_records_sql($sql, $filter['params']);
    }

    public function get_staff_rows(array $filters, int $page, int $perpage, bool $showidentity): array {
        global $DB;

        $filter = $this->user_filter_sql($filters, 'u', 'staffrows');
        $sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.department, u.city, u.timecreated, u.suspended
                  FROM {user} u
                 WHERE {$filter['sql']}
              ORDER BY u.lastname ASC, u.firstname ASC";

        $records = $DB->get_records_sql($sql, $filter['params'], $page * $perpage, $perpage);
        $rows = [];

        foreach ($records as $record) {
            $rows[] = [
                'cells' => [
                    ['key' => 'employee', 'value' => $showidentity ? fullname($record) : get_string('hiddenuser')],
                    ['key' => 'department', 'value' => (string)$record->department],
                    ['key' => 'location', 'value' => (string)$record->city],
                    ['key' => 'status', 'value' => $record->suspended ? 'Suspended' : 'Active'],
                    ['key' => 'created', 'value' => userdate((int)$record->timecreated, get_string('strftimedate'))],
                ],
            ];
        }

        return [
            'columns' => [
                ['key' => 'employee', 'label' => 'Employee'],
                ['key' => 'department', 'label' => 'Department'],
                ['key' => 'location', 'label' => 'Location'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'created', 'label' => 'Created'],
            ],
            'rows' => $rows,
            'totalcount' => $this->count_active_users($filters),
        ];
    }

    public function new_staff_risk_items(array $filters, int $days = 90, int $limit = 12): array {
        global $DB;

        $filter = $this->user_filter_sql($filters, 'u', 'newstaffrisk');
        $params = $filter['params'];
        $params['createdsince'] = time() - ($days * DAYSECS);

        $sql = "SELECT COALESCE(NULLIF(u.department, ''), 'Unassigned') AS department,
                       COUNT(1) AS newstaff
                  FROM {user} u
                 WHERE {$filter['sql']}
                   AND u.timecreated >= :createdsince
              GROUP BY COALESCE(NULLIF(u.department, ''), 'Unassigned')
              ORDER BY newstaff DESC";

        $records = $DB->get_records_sql($sql, $params, 0, $limit);
        $max = 1;
        foreach ($records as $record) {
            $max = max($max, (int)$record->newstaff);
        }

        $items = [];
        foreach ($records as $record) {
            $count = (int)$record->newstaff;
            $items[] = [
                'label' => (string)$record->department,
                'value' => (string)$count,
                'percent' => round(($count / $max) * 100, 1),
                'status' => $count > 20 ? 'danger' : ($count > 10 ? 'warning' : 'ok'),
                'meta' => 'created in last ' . $days . ' days',
            ];
        }

        return $items;
    }

    public function active_users_by_dimension_items(array $filters, string $dimension, int $limit = 12): array {
        global $DB;

        $allowed = [
            'department' => "COALESCE(NULLIF(u.department, ''), 'Unassigned')",
            'location' => "COALESCE(NULLIF(u.city, ''), 'Unassigned')",
        ];

        $expr = $allowed[$dimension] ?? $allowed['department'];
        $filter = $this->user_filter_sql($filters, 'u', 'userdim' . $dimension);

        $sql = "SELECT {$expr} AS label,
                       COUNT(1) AS usercount
                  FROM {user} u
                 WHERE {$filter['sql']}
              GROUP BY {$expr}
              ORDER BY usercount DESC";

        $records = $DB->get_records_sql($sql, $filter['params'], 0, $limit);
        $max = 1;
        foreach ($records as $record) {
            $max = max($max, (int)$record->usercount);
        }

        $items = [];
        foreach ($records as $record) {
            $count = (int)$record->usercount;
            $items[] = [
                'label' => (string)$record->label,
                'value' => (string)$count,
                'percent' => round(($count / $max) * 100, 1),
                'status' => 'info',
                'meta' => 'active users',
            ];
        }

        return $items;
    }

    public function user_filter_sql(array $filters, string $alias = 'u', string $prefix = 'flt'): array {
        global $CFG, $DB;

        $params = [];
        $where = [
            "{$alias}.deleted = 0",
            "{$alias}.confirmed = 1",
            "{$alias}.suspended = 0",
        ];

        if (!empty($CFG->siteguest)) {
            $guestkey = $prefix . 'guestid';
            $where[] = "{$alias}.id <> :{$guestkey}";
            $params[$guestkey] = (int)$CFG->siteguest;
        }

        if (!empty($filters['companyids'])) {
            $companyrepo = new company_repository();
            if ($companyrepo->has_iomad_tables()) {
                $companyrepo->append_user_company_filter($where, $params, $filters, $alias, $prefix);
            } else {
                [$insql, $inparams] = $DB->get_in_or_equal($filters['companyids'], SQL_PARAMS_NAMED, $prefix . 'company');
                $cohortalias = 'cm' . preg_replace('/[^a-z0-9]/i', '', $prefix);
                $where[] = "EXISTS (
                                SELECT 1
                                  FROM {cohort_members} {$cohortalias}
                                 WHERE {$cohortalias}.userid = {$alias}.id
                                   AND {$cohortalias}.cohortid {$insql}
                             )";
                $params += $inparams;
            }
        } else if (!empty($filters['companies'])) {
            $companyrepo = new company_repository();
            $companyrepo->append_user_company_filter($where, $params, $filters, $alias, $prefix);
        }

        if (!empty($filters['departments'])) {
            [$insql, $inparams] = $DB->get_in_or_equal($filters['departments'], SQL_PARAMS_NAMED, $prefix . 'department');
            $where[] = "{$alias}.department {$insql}";
            $params += $inparams;
        }

        if (!empty($filters['locations'])) {
            [$insql, $inparams] = $DB->get_in_or_equal($filters['locations'], SQL_PARAMS_NAMED, $prefix . 'location');
            $where[] = "{$alias}.city {$insql}";
            $params += $inparams;
        }

        if (!empty($filters['positions'])) {
            $positionfield = trim((string)get_config('block_dashboardanalytics', 'positionfield'));
            if ($positionfield !== '') {
                [$insql, $inparams] = $DB->get_in_or_equal($filters['positions'], SQL_PARAMS_NAMED, $prefix . 'position');
                $dataalias = 'uid' . preg_replace('/[^a-z0-9]/i', '', $prefix);
                $fieldalias = 'uif' . preg_replace('/[^a-z0-9]/i', '', $prefix);
                $fieldkey = $prefix . 'positionfield';
                $where[] = "EXISTS (
                                SELECT 1
                                  FROM {user_info_data} {$dataalias}
                                  JOIN {user_info_field} {$fieldalias} ON {$fieldalias}.id = {$dataalias}.fieldid
                                 WHERE {$dataalias}.userid = {$alias}.id
                                   AND {$fieldalias}.shortname = :{$fieldkey}
                                   AND {$dataalias}.data {$insql}
                             )";
                $params[$fieldkey] = $positionfield;
                $params += $inparams;
            }
        }

        if (!empty($filters['search'])) {
            $searchkey = $prefix . 'search';
            $searchfields = $DB->sql_concat("{$alias}.firstname", "' '", "{$alias}.lastname", "' '", "{$alias}.email");
            $where[] = $DB->sql_like($searchfields, ":{$searchkey}", false, false);
            $params[$searchkey] = '%' . $DB->sql_like_escape($filters['search']) . '%';
        }

        return [
            'sql' => implode(' AND ', $where),
            'params' => $params,
        ];
    }
}
