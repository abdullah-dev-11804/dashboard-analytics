<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

defined('MOODLE_INTERNAL') || die();

class employee_repository {
    /** @var int */
    private const SENTAL_COMPANY_ID = 1;
    /** @var string */
    private const SENTAL_ALLOWED_ROLE = 'student';

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
        $departmentexpr = $this->dimension_expr('departments', 'u', 'staffrowsdept');
        $locationexpr = $this->dimension_expr('locations', 'u', 'staffrowsloc');
        $sql = "SELECT u.id, u.firstname, u.lastname, u.email, {$departmentexpr} AS departmentname,
                       {$locationexpr} AS locationname, u.timecreated, u.suspended
                  FROM {user} u
                 WHERE {$filter['sql']}
              ORDER BY u.lastname ASC, u.firstname ASC";

        $records = $DB->get_records_sql($sql, $filter['params'], $page * $perpage, $perpage);
        $rows = [];

        foreach ($records as $record) {
            $rows[] = [
                'cells' => [
                    [
                        'key' => 'employee',
                        'value' => $showidentity ? fullname($record) : get_string('hiddenuser'),
                        'profileurl' => $showidentity ? (new \moodle_url('/user/profile.php', ['id' => (int)$record->id]))->out(false) : '',
                    ],
                    ['key' => 'department', 'value' => (string)$record->departmentname],
                    ['key' => 'location', 'value' => (string)$record->locationname],
                    ['key' => 'status', 'value' => $record->suspended ? get_string('label:suspended', 'block_dashboardanalytics') : get_string('label:active', 'block_dashboardanalytics')],
                    ['key' => 'created', 'value' => userdate((int)$record->timecreated, get_string('strftimedate'))],
                ],
            ];
        }

        return [
            'columns' => [
                ['key' => 'employee', 'label' => get_string('label:employee', 'block_dashboardanalytics')],
                ['key' => 'department', 'label' => get_string('label:department', 'block_dashboardanalytics')],
                ['key' => 'location', 'label' => get_string('label:location', 'block_dashboardanalytics')],
                ['key' => 'status', 'label' => get_string('label:status', 'block_dashboardanalytics')],
                ['key' => 'created', 'label' => get_string('label:created', 'block_dashboardanalytics')],
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

        $departmentexpr = $this->dimension_expr('departments', 'u', 'newstaffrisk');
        $sql = "SELECT {$departmentexpr} AS department,
                       COUNT(1) AS newstaff
                  FROM {user} u
                 WHERE {$filter['sql']}
                   AND u.timecreated >= :createdsince
              GROUP BY {$departmentexpr}
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
                'meta' => get_string('meta:createdlastdays', 'block_dashboardanalytics', $days),
            ];
        }

        return $items;
    }

    public function active_users_by_dimension_items(array $filters, string $dimension, int $limit = 12): array {
        global $DB;

        $allowed = [
            'department' => $this->dimension_expr('departments', 'u', 'userdimdept'),
            'location' => $this->dimension_expr('locations', 'u', 'userdimloc'),
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
                'meta' => get_string('meta:activeusers', 'block_dashboardanalytics'),
            ];
        }

        return $items;
    }

    public function staff_distribution_by_location_items(array $filters, int $limit = 8): array {
        global $DB;

        $filter = $this->user_filter_sql($filters, 'u', 'staffdist');
        $locationexpr = $this->dimension_expr('locations', 'u', 'staffdistloc');
        $departmentexpr = $this->dimension_expr('departments', 'u', 'staffdistdept');
        $sql = "SELECT {$locationexpr} AS location,
                       {$departmentexpr} AS department,
                       COUNT(1) AS usercount
                  FROM {user} u
                 WHERE {$filter['sql']}
              GROUP BY {$locationexpr},
                       {$departmentexpr}
              ORDER BY location ASC, usercount DESC";

        $records = $DB->get_records_sql($sql, $filter['params']);
        $locations = [];
        $max = 1;
        foreach ($records as $record) {
            $location = (string)$record->location;
            if (!isset($locations[$location])) {
                $locations[$location] = [];
            }
            $count = (int)$record->usercount;
            $max = max($max, $count);
            $locations[$location][] = [
                'label' => (string)$record->department,
                'value' => (string)$count,
                'percent' => 0.0,
                'status' => $this->department_status((string)$record->department),
            ];
        }

        $items = [];
        foreach (array_slice($locations, 0, $limit, true) as $location => $segments) {
            foreach ($segments as $index => $segment) {
                $segments[$index]['percent'] = round(((int)$segment['value'] / $max) * 100, 1);
            }
            $items[] = [
                'label' => $location,
                'value' => (string)array_sum(array_map('intval', array_column($segments, 'value'))),
                'percent' => 100.0,
                'status' => 'info',
                'meta' => get_string('meta:headcountbydepartment', 'block_dashboardanalytics'),
                'segments' => array_slice($segments, 0, 4),
            ];
        }

        return $items;
    }

    private function department_status(string $department): string {
        $department = strtolower($department);
        if (strpos($department, 'worker') !== false) {
            return 'info';
        }
        if (strpos($department, 'itr') !== false || strpos($department, 'engineer') !== false) {
            return 'ok';
        }
        return 'warning';
    }

    public function user_filter_sql(array $filters, string $alias = 'u', string $prefix = 'flt'): array {
        return $this->scoped_user_filter_sql($filters, $alias, $prefix);
    }

    public function scoped_user_filter_sql(
        array $filters,
        string $alias = 'u',
        string $prefix = 'flt',
        array $options = []
    ): array {
        global $CFG, $DB;

        $options = array_merge([
            'requireactive' => true,
            'requireconfirmed' => true,
            'includesuspended' => false,
            'includedeleted' => false,
        ], $options);

        $params = [];
        $where = [];

        if (!empty($options['requireactive'])) {
            $where[] = "{$alias}.deleted = 0";
            $where[] = "{$alias}.confirmed = 1";
            $where[] = "{$alias}.suspended = 0";
        } else {
            if (empty($options['includedeleted'])) {
                $where[] = "{$alias}.deleted = 0";
            }
            if (!empty($options['requireconfirmed'])) {
                $where[] = "{$alias}.confirmed = 1";
            }
            if (empty($options['includesuspended'])) {
                $where[] = "{$alias}.suspended = 0";
            }
        }

        if (!empty($CFG->siteguest)) {
            $guestkey = $prefix . 'guestid';
            $where[] = "{$alias}.id <> :{$guestkey}";
            $params[$guestkey] = (int)$CFG->siteguest;
        }

        if (!empty($filters['userids'])) {
            [$insql, $inparams] = $DB->get_in_or_equal($filters['userids'], SQL_PARAMS_NAMED, $prefix . 'user');
            $where[] = "{$alias}.id {$insql}";
            $params += $inparams;
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
            $this->append_profile_field_filter($where, $params, $filters['departments'], $this->profile_field_shortnames('departments'), 'department', $alias, $prefix . 'department');
        }

        if (!empty($filters['locations'])) {
            $this->append_profile_field_filter($where, $params, $filters['locations'], $this->profile_field_shortnames('locations'), 'city', $alias, $prefix . 'location');
        }

        if (!empty($filters['positions'])) {
            $this->append_profile_field_filter($where, $params, $filters['positions'], $this->profile_field_shortnames('positions'), '', $alias, $prefix . 'position');
        }

        if (!empty($filters['personnelcategories'])) {
            $this->append_profile_field_filter($where, $params, $filters['personnelcategories'], $this->profile_field_shortnames('personnelcategories'), '', $alias, $prefix . 'personnelcategory');
        }

        if (!empty($filters['sites'])) {
            $this->append_profile_field_filter($where, $params, $filters['sites'], $this->profile_field_shortnames('sites'), '', $alias, $prefix . 'site');
        }

        if (!empty($filters['educations'])) {
            $this->append_profile_field_filter($where, $params, $filters['educations'], $this->profile_field_shortnames('educations'), '', $alias, $prefix . 'education');
        }

        if (!empty($filters['search'])) {
            $searchkey = $prefix . 'search';
            $searchfields = $DB->sql_concat("{$alias}.firstname", "' '", "{$alias}.lastname", "' '", "{$alias}.email");
            $where[] = $DB->sql_like($searchfields, ":{$searchkey}", false, false);
            $params[$searchkey] = '%' . $DB->sql_like_escape($filters['search']) . '%';
        }

        $this->append_sental_student_only_filter($where, $params, $alias, $prefix);

        return [
            'sql' => implode(' AND ', $where),
            'params' => $params,
        ];
    }

    public function append_sental_student_only_filter(
        array &$where,
        array &$params,
        string $alias = 'u',
        string $prefix = 'flt'
    ): void {
        $companyrepo = new company_repository();
        if (!$companyrepo->has_iomad_tables()) {
            return;
        }

        $sentalcompanykey = $prefix . 'sentalcompanyid';
        $sentalcompanykeyallow = $prefix . 'sentalcompanyidallow';
        $sentalrolekey = $prefix . 'sentalroleshortname';
        $sentalcompanyalias = 'cus' . preg_replace('/[^a-z0-9]/i', '', $prefix);
        $sentalallowalias = 'cua' . preg_replace('/[^a-z0-9]/i', '', $prefix);
        $roleassignalias = 'ras' . preg_replace('/[^a-z0-9]/i', '', $prefix);
        $rolealias = 'rol' . preg_replace('/[^a-z0-9]/i', '', $prefix);

        $where[] = "(NOT EXISTS (
                            SELECT 1
                              FROM {company_users} {$sentalcompanyalias}
                             WHERE {$sentalcompanyalias}.userid = {$alias}.id
                               AND {$sentalcompanyalias}.companyid = :{$sentalcompanykey}
                         )
                         OR EXISTS (
                            SELECT 1
                              FROM {company_users} {$sentalallowalias}
                              JOIN {role_assignments} {$roleassignalias}
                                ON {$roleassignalias}.userid = {$alias}.id
                              JOIN {role} {$rolealias}
                                ON {$rolealias}.id = {$roleassignalias}.roleid
                             WHERE {$sentalallowalias}.userid = {$alias}.id
                               AND {$sentalallowalias}.companyid = :{$sentalcompanykeyallow}
                          GROUP BY {$sentalallowalias}.userid
                            HAVING COUNT(DISTINCT {$rolealias}.shortname) = 1
                               AND MAX({$rolealias}.shortname) = :{$sentalrolekey}
                         ))";

        $params[$sentalcompanykey] = self::SENTAL_COMPANY_ID;
        $params[$sentalcompanykeyallow] = self::SENTAL_COMPANY_ID;
        $params[$sentalrolekey] = self::SENTAL_ALLOWED_ROLE;
    }

    private function profile_field_exists(string $shortname): bool {
        global $DB;

        return $DB->record_exists('user_info_field', ['shortname' => $shortname]);
    }

    private function append_profile_field_filter(
        array &$where,
        array &$params,
        array $values,
        array $shortnames,
        string $fallbackcolumn,
        string $alias,
        string $prefix
    ): void {
        global $DB;

        [$insql, $inparams] = $DB->get_in_or_equal($values, SQL_PARAMS_NAMED, $prefix);
        $shortname = $this->existing_profile_field($shortnames);
        if ($shortname !== '') {
            $dataalias = 'uid' . preg_replace('/[^a-z0-9]/i', '', $prefix);
            $fieldalias = 'uif' . preg_replace('/[^a-z0-9]/i', '', $prefix);
            $fieldkey = $prefix . 'field';
            $where[] = "EXISTS (
                            SELECT 1
                              FROM {user_info_data} {$dataalias}
                              JOIN {user_info_field} {$fieldalias} ON {$fieldalias}.id = {$dataalias}.fieldid
                             WHERE {$dataalias}.userid = {$alias}.id
                               AND {$fieldalias}.shortname = :{$fieldkey}
                               AND {$dataalias}.data {$insql}
                         )";
            $params[$fieldkey] = $shortname;
            $params += $inparams;
            return;
        }

        if ($fallbackcolumn !== '') {
            $where[] = "{$alias}.{$fallbackcolumn} {$insql}";
            $params += $inparams;
        }
    }

    private function existing_profile_field(array $shortnames): string {
        foreach ($shortnames as $shortname) {
            if ($shortname !== '' && $this->profile_field_exists($shortname)) {
                return $shortname;
            }
        }
        return '';
    }

    private function profile_field_shortnames(string $key): array {
        if ($key === 'departments') {
            return ['Department', 'department'];
        }
        if ($key === 'locations') {
            return ['Region'];
        }
        if ($key === 'positions') {
            return array_values(array_filter(['Job_Title', trim((string)get_config('block_dashboardanalytics', 'positionfield'))]));
        }
        if ($key === 'personnelcategories') {
            return ['PersonnelCategory'];
        }
        if ($key === 'sites') {
            return ['Site'];
        }
        if ($key === 'educations') {
            return ['edu'];
        }
        return [];
    }

    private function dimension_expr(string $key, string $alias, string $prefix): string {
        $fallback = $key === 'departments'
            ? "{$alias}.department"
            : ($key === 'locations' ? "{$alias}.city" : "''");
        $shortname = $this->existing_profile_field($this->profile_field_shortnames($key));
        if ($shortname === '') {
            return "COALESCE(NULLIF({$fallback}, ''), '" . get_string('label:unassigned', 'block_dashboardanalytics') . "')";
        }

        $escaped = addslashes($shortname);
        return "COALESCE(NULLIF((
                    SELECT uid.data
                      FROM {user_info_data} uid
                      JOIN {user_info_field} uif ON uif.id = uid.fieldid
                     WHERE uid.userid = {$alias}.id
                       AND uif.shortname = '{$escaped}'
                     LIMIT 1
                ), ''), " . ($fallback !== "''" ? "NULLIF({$fallback}, '')" : "''") . ", '" . get_string('label:unassigned', 'block_dashboardanalytics') . "')";
    }
}
