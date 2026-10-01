<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

defined('MOODLE_INTERNAL') || die();

/**
 * Stores company-specific employee state and immutable employment events.
 */
class employee_status_repository {
    /** @var array<string, bool> */
    private static array $tableexistscache = [];

    public function is_deactivated(int $userid, int $companyid): bool {
        global $DB;

        if ($userid <= 0 || $companyid <= 0 || !$this->table_exists('block_da_empstatus')) {
            return false;
        }

        return $DB->record_exists('block_da_empstatus', [
            'userid' => $userid,
            'companyid' => $companyid,
            'deactivated' => 1,
        ]);
    }

    /**
     * Return a SQL condition which keeps users active in the requested company scope.
     *
     * @return array{sql: string, params: array}
     */
    public function active_user_filter_sql(array $filters, string $useralias, string $prefix): array {
        global $DB;

        if (!$this->table_exists('block_da_empstatus') || !$this->table_exists('company_users')) {
            return ['sql' => '1 = 1', 'params' => []];
        }

        $safe = preg_replace('/[^a-z0-9]/i', '', $prefix);
        $companyuseralias = 'cuactive' . $safe;
        $statusalias = 'esactive' . $safe;
        $params = [];
        $companywhere = [];

        if (!empty($filters['companyids'])) {
            [$insql, $inparams] = $DB->get_in_or_equal(
                array_values(array_unique(array_map('intval', $filters['companyids']))),
                SQL_PARAMS_NAMED,
                $prefix . 'activecompany'
            );
            $companywhere[] = "{$companyuseralias}.companyid {$insql}";
            $params += $inparams;
        } else if (!empty($filters['companies']) && $this->table_exists('company')) {
            $companyalias = 'coactive' . $safe;
            [$insql, $inparams] = $DB->get_in_or_equal(
                array_values(array_unique(array_map('strval', $filters['companies']))),
                SQL_PARAMS_NAMED,
                $prefix . 'activecompanyname'
            );
            $companywhere[] = "EXISTS (
                                SELECT 1
                                  FROM {company} {$companyalias}
                                 WHERE {$companyalias}.id = {$companyuseralias}.companyid
                                   AND {$companyalias}.name {$insql}
                              )";
            $params += $inparams;
        }

        if ($companywhere) {
            return [
                'sql' => "EXISTS (
                            SELECT 1
                              FROM {company_users} {$companyuseralias}
                             WHERE {$companyuseralias}.userid = {$useralias}.id
                               AND " . implode(' AND ', $companywhere) . "
                               AND NOT EXISTS (
                                    SELECT 1
                                      FROM {block_da_empstatus} {$statusalias}
                                     WHERE {$statusalias}.userid = {$companyuseralias}.userid
                                       AND {$statusalias}.companyid = {$companyuseralias}.companyid
                                       AND {$statusalias}.deactivated = 1
                               )
                          )",
                'params' => $params,
            ];
        }

        return [
            'sql' => "(
                        NOT EXISTS (
                            SELECT 1
                              FROM {block_da_empstatus} {$statusalias}
                             WHERE {$statusalias}.userid = {$useralias}.id
                               AND {$statusalias}.deactivated = 1
                        )
                        OR EXISTS (
                            SELECT 1
                              FROM {company_users} {$companyuseralias}
                             WHERE {$companyuseralias}.userid = {$useralias}.id
                               AND NOT EXISTS (
                                    SELECT 1
                                      FROM {block_da_empstatus} {$statusalias}2
                                     WHERE {$statusalias}2.userid = {$companyuseralias}.userid
                                       AND {$statusalias}2.companyid = {$companyuseralias}.companyid
                                       AND {$statusalias}2.deactivated = 1
                               )
                        )
                      )",
            'params' => [],
        ];
    }

    /**
     * SQL appended to a company_users join to hide deactivated memberships.
     */
    public function active_membership_join_sql(string $companyuseralias, string $prefix): string {
        if (!$this->table_exists('block_da_empstatus')) {
            return '';
        }

        $statusalias = 'esjoin' . preg_replace('/[^a-z0-9]/i', '', $prefix);
        return " AND NOT EXISTS (
                    SELECT 1
                      FROM {block_da_empstatus} {$statusalias}
                     WHERE {$statusalias}.userid = {$companyuseralias}.userid
                       AND {$statusalias}.companyid = {$companyuseralias}.companyid
                       AND {$statusalias}.deactivated = 1
                 )";
    }

    /**
     * Return a SQL condition which keeps a specific user/company pair active.
     */
    public function active_company_filter_sql(
        string $useridexpression,
        string $companyidexpression,
        string $prefix
    ): string {
        if (!$this->table_exists('block_da_empstatus')) {
            return '1 = 1';
        }

        $statusalias = 'escompany' . preg_replace('/[^a-z0-9]/i', '', $prefix);
        return "NOT EXISTS (
                    SELECT 1
                      FROM {block_da_empstatus} {$statusalias}
                     WHERE {$statusalias}.userid = {$useridexpression}
                       AND {$statusalias}.companyid = {$companyidexpression}
                       AND {$statusalias}.deactivated = 1
                )";
    }

    public function list_employees(
        string $search = '',
        int $companyid = 0,
        string $status = 'active',
        int $page = 0,
        int $perpage = 20
    ): array {
        global $DB;

        $page = max(0, $page);
        $perpage = max(10, min(100, $perpage));
        $status = in_array($status, ['active', 'deactivated', 'all'], true) ? $status : 'active';
        $params = [];
        $where = ['u.deleted = 0', 'u.confirmed = 1'];

        if ($companyid > 0) {
            $where[] = 'cu.companyid = :employeecompanyid';
            $params['employeecompanyid'] = $companyid;
        }

        if ($status === 'deactivated') {
            $where[] = 'COALESCE(es.deactivated, 0) = 1';
        } else if ($status === 'active') {
            $where[] = 'COALESCE(es.deactivated, 0) = 0';
        }

        if ($search !== '') {
            $searchvalue = '%' . $DB->sql_like_escape($search) . '%';
            $searchfields = $DB->sql_concat('u.firstname', "' '", 'u.lastname', "' '", 'u.email');
            $where[] = '(' . $DB->sql_like($searchfields, ':employeesearch', false, false) . ' OR ' .
                $DB->sql_like('c.name', ':employeecompanysearch', false, false) . ')';
            $params['employeesearch'] = $searchvalue;
            $params['employeecompanysearch'] = $searchvalue;
        }

        $from = "FROM {company_users} cu
                 JOIN {user} u ON u.id = cu.userid
                 JOIN {company} c ON c.id = cu.companyid
            LEFT JOIN {block_da_empstatus} es
                   ON es.userid = cu.userid
                  AND es.companyid = cu.companyid";
        $groupby = 'u.id, u.firstname, u.lastname, u.email, u.suspended, c.id, c.name, ' .
            'es.deactivated, es.deactivatedat, es.timemodified';
        $wheresql = implode(' AND ', $where);
        $sql = "SELECT " . $DB->sql_concat('u.id', "':'", 'c.id') . " AS rowid,
                       u.id AS userid,
                       u.firstname,
                       u.lastname,
                       u.email,
                       u.suspended,
                       c.id AS companyid,
                       c.name AS companyname,
                       COALESCE(es.deactivated, 0) AS deactivated,
                       COALESCE(es.deactivatedat, 0) AS deactivatedat,
                       COALESCE(es.timemodified, 0) AS statemodified
                  {$from}
                 WHERE {$wheresql}
              GROUP BY {$groupby}
              ORDER BY c.name ASC, u.lastname ASC, u.firstname ASC, u.id ASC";
        $countsql = "SELECT COUNT(1)
                       FROM (
                            SELECT u.id, c.id AS companyid
                              {$from}
                             WHERE {$wheresql}
                          GROUP BY u.id, c.id
                       ) countedmemberships";

        $rows = [];
        foreach ($DB->get_records_sql($sql, $params, $page * $perpage, $perpage) as $record) {
            $rows[] = [
                'userid' => (int)$record->userid,
                'fullname' => fullname($record),
                'email' => (string)$record->email,
                'companyid' => (int)$record->companyid,
                'companyname' => format_string((string)$record->companyname),
                'deactivated' => !empty($record->deactivated),
                'accountsuspended' => !empty($record->suspended),
                'deactivatedat' => (int)$record->deactivatedat,
            ];
        }

        $companyoptions = [];
        foreach ($DB->get_records('company', null, 'name ASC', 'id, name', 0, 500) as $company) {
            $companyoptions[] = [
                'value' => (int)$company->id,
                'label' => format_string((string)$company->name),
            ];
        }

        return [
            'rows' => $rows,
            'companies' => $companyoptions,
            'totalcount' => (int)$DB->count_records_sql($countsql, $params),
            'page' => $page,
            'perpage' => $perpage,
            'status' => $status,
            'companyid' => $companyid,
        ];
    }

    /**
     * Change one employee/company state. Repeating the current state is a no-op.
     */
    public function set_deactivated(int $userid, int $companyid, bool $deactivated, int $actorid): array {
        global $DB;

        if (!$this->table_exists('company_users')) {
            throw new \moodle_exception('error:iomadrequired', 'block_dashboardanalytics');
        }

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], 'id, firstname, lastname', IGNORE_MISSING);
        $company = $DB->get_record('company', ['id' => $companyid], 'id, name', IGNORE_MISSING);
        $membershipid = (int)$DB->get_field_sql(
            'SELECT MIN(id) FROM {company_users} WHERE userid = :userid AND companyid = :companyid',
            ['userid' => $userid, 'companyid' => $companyid]
        );
        if (!$user || !$company || $membershipid <= 0) {
            throw new \moodle_exception('error:invalidemployeecompany', 'block_dashboardanalytics');
        }

        $lockfactory = \core\lock\lock_config::get_lock_factory('block_dashboardanalytics_employee_status');
        $lock = $lockfactory->get_lock($userid . ':' . $companyid, 10);
        if (!$lock) {
            throw new \moodle_exception('error:employeestatusbusy', 'block_dashboardanalytics');
        }

        try {
            $transaction = $DB->start_delegated_transaction();
            try {
                $state = $DB->get_record('block_da_empstatus', [
                    'userid' => $userid,
                    'companyid' => $companyid,
                ], '*', IGNORE_MISSING);
                $current = $state ? !empty($state->deactivated) : false;
                if ($current === $deactivated) {
                    $result = ['changed' => false, 'deactivated' => $current];
                } else {
                    $now = time();
                    $record = (object)[
                        'userid' => $userid,
                        'companyid' => $companyid,
                        'deactivated' => $deactivated ? 1 : 0,
                        'deactivatedat' => $deactivated ? $now : 0,
                        'deactivatedby' => $deactivated ? $actorid : 0,
                        'timemodified' => $now,
                        'modifiedby' => $actorid,
                    ];
                    if ($state) {
                        $record->id = (int)$state->id;
                        $DB->update_record('block_da_empstatus', $record);
                    } else {
                        $record->timecreated = $now;
                        $record->id = $DB->insert_record('block_da_empstatus', $record);
                    }

                    $action = $deactivated ? 'deactivate' : 'activate';
                    $sourcekey = 'manual:' . $action . ':' . $userid . ':' . $companyid . ':'
                        . microtime(true) . ':' . random_int(0, PHP_INT_MAX);
                    $this->record_event(
                        $userid,
                        $companyid,
                        $action,
                        $actorid,
                        $now,
                        $sourcekey,
                        (string)$company->name,
                        '',
                        false
                    );
                    $result = ['changed' => true, 'deactivated' => $deactivated];
                }
                $transaction->allow_commit();
            } catch (\Throwable $exception) {
                $transaction->rollback($exception);
            }
        } finally {
            $lock->release();
        }

        if ($result['changed']) {
            \cache_helper::purge_by_definition('block_dashboardanalytics', 'filter_options');
            \cache_helper::purge_by_definition('block_dashboardanalytics', 'kpi_results');
        }

        return $result;
    }

    public function record_event(
        int $userid,
        int $companyid,
        string $action,
        int $actorid,
        int $timecreated,
        string $sourcekey,
        string $companyname = '',
        string $detail = '',
        bool $deduplicatenearby = true
    ): int {
        global $DB;

        if ($userid <= 0 || $companyid <= 0 || !$this->table_exists('block_da_empaudit')) {
            return 0;
        }

        $sourcekey = sha1($sourcekey);
        $existing = (int)$DB->get_field('block_da_empaudit', 'id', ['sourcekey' => $sourcekey], IGNORE_MISSING);
        if ($existing > 0) {
            return $existing;
        }

        if ($deduplicatenearby) {
            $duplicate = (int)$DB->get_field_sql(
                'SELECT MIN(id)
                   FROM {block_da_empaudit}
                  WHERE userid = :userid
                    AND companyid = :companyid
                    AND action = :action
                    AND timecreated >= :timestart
                    AND timecreated <= :timeend',
                [
                    'userid' => $userid,
                    'companyid' => $companyid,
                    'action' => $action,
                    'timestart' => max(0, $timecreated - 2),
                    'timeend' => $timecreated + 2,
                ]
            );
            if ($duplicate > 0) {
                return $duplicate;
            }
        }

        if ($companyname === '' && $this->table_exists('company')) {
            $companyname = (string)$DB->get_field('company', 'name', ['id' => $companyid], IGNORE_MISSING);
        }

        return (int)$DB->insert_record('block_da_empaudit', (object)[
            'userid' => $userid,
            'companyid' => $companyid,
            'companyname' => $companyname,
            'action' => $action,
            'actorid' => max(0, $actorid),
            'sourcekey' => $sourcekey,
            'detail' => $detail,
            'timecreated' => $timecreated > 0 ? $timecreated : time(),
        ]);
    }

    public function departure_events(array $companyids, int $start, int $end): array {
        global $DB;

        $companyids = array_values(array_unique(array_filter(array_map('intval', $companyids))));
        if (!$companyids || !$this->table_exists('block_da_empaudit')) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($companyids, SQL_PARAMS_NAMED, 'employmentexitcompany');
        [$actionsql, $actionparams] = $DB->get_in_or_equal(
            ['deactivate', 'companychange', 'deleted', 'suspended'],
            SQL_PARAMS_NAMED,
            'employmentexitaction'
        );
        $params += $actionparams;
        $where = ["a.companyid {$insql}", "a.action {$actionsql}"];
        if ($start > 0) {
            $where[] = 'a.timecreated >= :employmentexitstart';
            $params['employmentexitstart'] = $start;
        }
        if ($end > 0) {
            $where[] = 'a.timecreated <= :employmentexitend';
            $params['employmentexitend'] = $end;
        }
        $params['employmentexithirefield'] = 'Date';
        $params['employmentexitsitefield'] = 'Site';

        $sql = "SELECT a.id AS auditid,
                       a.userid,
                       a.companyid,
                       a.companyname,
                       a.action,
                       a.timecreated AS exittimestamp,
                       u.timecreated,
                       u.timemodified,
                       u.suspended,
                       u.deleted,
                       u.firstname,
                       u.lastname,
                       u.email,
                       hiredata.data AS hiredateprofile,
                       COALESCE(NULLIF(sitedata.data, ''), '') AS site
                  FROM {block_da_empaudit} a
             LEFT JOIN {user} u ON u.id = a.userid
             LEFT JOIN {user_info_field} hirefield
                    ON hirefield.shortname = :employmentexithirefield
             LEFT JOIN {user_info_data} hiredata
                    ON hiredata.fieldid = hirefield.id
                   AND hiredata.userid = a.userid
             LEFT JOIN {user_info_field} sitefield
                    ON sitefield.shortname = :employmentexitsitefield
             LEFT JOIN {user_info_data} sitedata
                    ON sitedata.fieldid = sitefield.id
                   AND sitedata.userid = a.userid
                 WHERE " . implode(' AND ', $where) . '
              ORDER BY a.timecreated ASC, a.id ASC';

        return array_values($DB->get_records_sql($sql, $params));
    }

    public function table_exists(string $tablename): bool {
        global $DB;

        if (!array_key_exists($tablename, self::$tableexistscache)) {
            self::$tableexistscache[$tablename] = $DB->get_manager()->table_exists(new \xmldb_table($tablename));
        }
        return self::$tableexistscache[$tablename];
    }
}
