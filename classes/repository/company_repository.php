<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

use block_dashboardanalytics\permissions;

defined('MOODLE_INTERNAL') || die();

class company_repository {

    public function has_iomad_tables(): bool {
        return $this->table_exists('company') && $this->table_exists('company_users');
    }

    public function get_company_options(array $filters = []): array {
        global $DB;

        if ($this->has_iomad_tables()) {
            $where = '';
            $params = [];
            if (!empty($filters['companyids'])) {
                [$insql, $params] = $DB->get_in_or_equal($filters['companyids'], SQL_PARAMS_NAMED, 'companyoption');
                $where = "id {$insql}";
            } else if (!empty($filters['allowedcompanyids'])) {
                [$insql, $params] = $DB->get_in_or_equal($filters['allowedcompanyids'], SQL_PARAMS_NAMED, 'companyoptionallowed');
                $where = "id {$insql}";
            }

            $records = $where === ''
                ? $DB->get_records('company', null, 'name ASC', 'id, name', 0, 500)
                : $DB->get_records_select('company', $where, $params, 'name ASC', 'id, name', 0, 500);
            $options = [];
            foreach ($records as $record) {
                $options[] = ['value' => (string)$record->id, 'label' => format_string($record->name)];
            }
            return $options;
        }

        $profilefield = $this->company_profile_shortname();
        if ($profilefield === '') {
            return [];
        }

        $params = ['companyprofileshortname' => $profilefield];
        $where = [
            "u.deleted = 0",
            "uid.data <> ''",
            "uif.shortname = :companyprofileshortname",
        ];

        if (!empty($filters['companies'])) {
            [$insql, $inparams] = $DB->get_in_or_equal($filters['companies'], SQL_PARAMS_NAMED, 'companynameoption');
            $where[] = "uid.data {$insql}";
            $params += $inparams;
        }

        $sql = "SELECT DISTINCT uid.data
                  FROM {user_info_data} uid
                  JOIN {user_info_field} uif ON uif.id = uid.fieldid
                  JOIN {user} u ON u.id = uid.userid
                 WHERE " . implode(' AND ', $where) . "
              ORDER BY uid.data ASC";

        return $this->text_options($DB->get_fieldset_sql($sql, $params));
    }

    public function company_filter_key(array $filters = []): string {
        return $this->has_iomad_tables() ? 'companyids' : 'companies';
    }

    public function scope_filters_for_user(int $userid): array {
        $details = $this->scope_details_for_user($userid);
        if (!empty($details['companyids'])) {
            return ['companyids' => $details['companyids']];
        }

        if (!empty($details['companies'])) {
            return ['companies' => $details['companies']];
        }

        return [];
    }

    public function scope_details_for_user(int $userid): array {
        global $DB;

        if ($this->has_iomad_tables()) {
            $records = $DB->get_records('company_users', ['userid' => $userid], '', 'id, companyid');
            $companyids = [];
            foreach ($records as $record) {
                $companyid = (int)$record->companyid;
                if ($companyid > 0) {
                    $companyids[] = $companyid;
                }
            }

            $companyids = array_values(array_unique($companyids));
            if ($companyids) {
                sort($companyids);
                return [
                    'companyids' => $companyids,
                    'selectorvisible' => count($companyids) > 1,
                ];
            }

            return [
                'companyids' => [],
                'selectorvisible' => false,
            ];
        }

        $companyname = $this->profile_company_for_user($userid);
        if ($companyname === '') {
            return [
                'companies' => [],
                'selectorvisible' => false,
            ];
        }

        if ($this->has_iomad_tables()) {
            $mappedid = (int)$DB->get_field('company', 'id', ['name' => $companyname], IGNORE_MISSING);
            if ($mappedid > 0) {
                return [
                    'companyids' => [$mappedid],
                    'selectorvisible' => false,
                ];
            }
        }

        return [
            'companies' => [$companyname],
            'selectorvisible' => false,
        ];
    }

    public function company_name_sql(string $useralias, string $prefix): array {
        if ($this->has_iomad_tables()) {
            $companyuseralias = 'cu' . preg_replace('/[^a-z0-9]/i', '', $prefix);
            $companyalias = 'co' . preg_replace('/[^a-z0-9]/i', '', $prefix);
            $expr = "NULLIF({$companyalias}.name, '')";
            $idexpr = "{$companyalias}.id";
            return [
                'join' => "LEFT JOIN {company_users} {$companyuseralias} ON {$companyuseralias}.userid = {$useralias}.id
                           LEFT JOIN {company} {$companyalias} ON {$companyalias}.id = {$companyuseralias}.companyid",
                'expr' => $expr,
                'idexpr' => $idexpr,
                'select' => "{$expr} AS companyname",
            ];
        }

        $profilejoin = $this->company_profile_join_sql($useralias, $prefix);
        $expr = $profilejoin['dataalias'] !== '' ? "NULLIF({$profilejoin['dataalias']}.data, '')" : "NULL";
        return [
            'join' => $profilejoin['join'],
            'expr' => $expr,
            'idexpr' => 'NULL',
            'select' => "{$expr} AS companyname",
        ];
    }

    public function append_user_company_filter(array &$where, array &$params, array $filters, string $useralias, string $prefix): void {
        global $DB;

        if ($this->has_iomad_tables() && !empty($filters['companyids'])) {
            [$insql, $inparams] = $DB->get_in_or_equal($filters['companyids'], SQL_PARAMS_NAMED, $prefix . 'company');
            $companyuseralias = 'cuf' . preg_replace('/[^a-z0-9]/i', '', $prefix);
            $clauses = ["EXISTS (
                            SELECT 1
                              FROM {company_users} {$companyuseralias}
                             WHERE {$companyuseralias}.userid = {$useralias}.id
                               AND {$companyuseralias}.companyid {$insql}
                         )"];
            $params += $inparams;

            $where[] = '(' . implode(' OR ', $clauses) . ')';
            return;
        }

        if (!empty($filters['companies'])) {
            $clauses = [];
            if ($this->has_iomad_tables()) {
                [$tableinsql, $tableparams] = $DB->get_in_or_equal($filters['companies'], SQL_PARAMS_NAMED, $prefix . 'companynameiomad');
                $companyuseralias = 'cut' . preg_replace('/[^a-z0-9]/i', '', $prefix);
                $companyalias = 'cot' . preg_replace('/[^a-z0-9]/i', '', $prefix);
                $clauses[] = "EXISTS (
                                SELECT 1
                                  FROM {company_users} {$companyuseralias}
                                  JOIN {company} {$companyalias} ON {$companyalias}.id = {$companyuseralias}.companyid
                                 WHERE {$companyuseralias}.userid = {$useralias}.id
                                   AND {$companyalias}.name {$tableinsql}
                             )";
                $params += $tableparams;
            }

            if ($clauses) {
                $where[] = '(' . implode(' OR ', $clauses) . ')';
            }
        }
    }

    public function active_user_aggregate(array $filters): array {
        global $DB;

        $employee = new employee_repository();
        $documents = new document_repository();
        $userfilter = $employee->user_filter_sql($filters, 'u', 'companyagg');
        $company = $this->company_name_sql('u', 'companyagg');

        $sql = "SELECT COALESCE({$company['idexpr']}, 0) AS companyid,
                       COALESCE({$company['expr']}, 'Unassigned') AS companyname,
                       COUNT(DISTINCT u.id) AS activeusers
                  FROM {user} u
                       {$company['join']}
                 WHERE {$userfilter['sql']}
              GROUP BY COALESCE({$company['idexpr']}, 0), COALESCE({$company['expr']}, 'Unassigned')
              ORDER BY activeusers DESC, companyname ASC";

        $records = $DB->get_records_sql($sql, $userfilter['params']);
        $rows = [];
        foreach ($records as $record) {
            $companyfilters = $this->filters_for_company($filters, (string)$record->companyname);
            if ($this->has_iomad_tables() && !empty($record->companyid)) {
                $companyfilters['companyids'] = [(int)$record->companyid];
            }
            $summary = $documents->compliance_summary($companyfilters);
            $compliance = $summary['compliance'];
            $status = $summary['status'];

            $rows[] = [
                'cells' => [
                    ['key' => 'companyid', 'value' => (string)(int)$record->companyid],
                    ['key' => 'company', 'value' => (string)$record->companyname],
                    ['key' => 'activeusers', 'value' => (string)(int)$record->activeusers],
                    ['key' => 'compliance', 'value' => $compliance . '%'],
                    ['key' => 'status', 'value' => $status],
                    ['key' => 'action', 'value' => 'View full report'],
                ],
            ];
        }

        return [
            'columns' => [
                ['key' => 'company', 'label' => get_string('label:company', 'block_dashboardanalytics')],
                ['key' => 'activeusers', 'label' => get_string('label:activeusers', 'block_dashboardanalytics')],
                ['key' => 'compliance', 'label' => get_string('label:compliancepercent', 'block_dashboardanalytics')],
                ['key' => 'status', 'label' => get_string('label:status', 'block_dashboardanalytics')],
                ['key' => 'action', 'label' => get_string('label:action', 'block_dashboardanalytics')],
            ],
            'rows' => $rows,
            'totalcount' => count($rows),
            'notice' => '',
            'description' => '',
        ];
    }

    public function compliance_items(array $filters, int $limit = 12): array {
        global $DB;

        $employee = new employee_repository();
        $documents = new document_repository();
        $userfilter = $employee->user_filter_sql($filters, 'u', 'companychart');
        $company = $this->company_name_sql('u', 'companychart');

        $sql = "SELECT COALESCE({$company['idexpr']}, 0) AS companyid,
                       COALESCE({$company['expr']}, 'Unassigned') AS companyname,
                       COUNT(DISTINCT u.id) AS activeusers
                  FROM {user} u
                       {$company['join']}
                 WHERE {$userfilter['sql']}
              GROUP BY COALESCE({$company['idexpr']}, 0), COALESCE({$company['expr']}, 'Unassigned')
              ORDER BY companyname ASC";

        $records = $DB->get_records_sql($sql, $userfilter['params'], 0, $limit);
        $items = [];
        foreach ($records as $record) {
            $companyfilters = $this->filters_for_company($filters, (string)$record->companyname);
            if ($this->has_iomad_tables() && !empty($record->companyid)) {
                $companyfilters['companyids'] = [(int)$record->companyid];
            }
            $summary = $documents->compliance_summary($companyfilters);
            $items[] = [
                'label' => (string)$record->companyname,
                'value' => $summary['compliance'] . '%',
                'percent' => (float)$summary['compliance'],
                'status' => strtolower($summary['status']),
                'meta' => get_string('meta:fullycompliantemployees', 'block_dashboardanalytics', (object)[
                    'compliant' => $summary['validusers'],
                    'total' => $summary['totalactiveusers'],
                ]),
            ];
        }

        usort($items, static function(array $a, array $b): int {
            return $a['percent'] <=> $b['percent'];
        });

        return $items;
    }

    private function filters_for_company(array $filters, string $companyname): array {
        if ($companyname !== '') {
            $filters['companies'] = [$companyname];
        }
        return $filters;
    }

    private function table_exists(string $tablename): bool {
        global $CFG, $DB;

        require_once($CFG->libdir . '/xmldb/xmldb_table.php');
        return $DB->get_manager()->table_exists(new \xmldb_table($tablename));
    }

    private function company_profile_shortname(): string {
        global $DB;

        return $DB->record_exists('user_info_field', ['shortname' => 'Company']) ? 'Company' : '';
    }

    private function profile_company_for_user(int $userid): string {
        global $DB;

        $shortname = $this->company_profile_shortname();
        if ($shortname === '') {
            return '';
        }

        $sql = "SELECT uid.data
                  FROM {user_info_data} uid
                  JOIN {user_info_field} uif ON uif.id = uid.fieldid
                 WHERE uid.userid = :userid
                   AND uif.shortname = :shortname";

        return trim((string)$DB->get_field_sql($sql, ['userid' => $userid, 'shortname' => $shortname]));
    }

    private function company_names_by_ids(array $companyids): array {
        global $DB;

        $companyids = array_values(array_filter(array_map('intval', $companyids)));
        if (!$companyids) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($companyids, SQL_PARAMS_NAMED, 'companynamebyid');
        $sql = "SELECT name
                  FROM {company}
                 WHERE id {$insql}";

        return $this->text_options($DB->get_fieldset_sql($sql, $params), true);
    }

    private function company_profile_join_sql(string $useralias, string $prefix): array {
        $shortname = $this->company_profile_shortname();
        if ($shortname === '') {
            return [
                'join' => '',
                'dataalias' => '',
            ];
        }

        $suffix = preg_replace('/[^a-z0-9]/i', '', $prefix);
        $fieldalias = 'uifcomp' . $suffix;
        $dataalias = 'uidcomp' . $suffix;
        $escaped = addslashes($shortname);

        return [
            'join' => "LEFT JOIN {user_info_field} {$fieldalias} ON {$fieldalias}.shortname = '{$escaped}'
                       LEFT JOIN {user_info_data} {$dataalias} ON {$dataalias}.fieldid = {$fieldalias}.id AND {$dataalias}.userid = {$useralias}.id",
            'dataalias' => $dataalias,
        ];
    }

    private function text_options(array $values, bool $raw = false): array {
        $options = [];
        foreach ($values as $value) {
            $value = trim((string)$value);
            if ($value !== '') {
                $options[] = $raw ? $value : ['value' => $value, 'label' => $value];
            }
        }
        return $options;
    }
}
