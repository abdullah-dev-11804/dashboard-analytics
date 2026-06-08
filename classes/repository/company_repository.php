<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

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

        $where = ["deleted = 0", "department <> ''"];
        $params = [];
        if (!empty($filters['companies'])) {
            [$insql, $params] = $DB->get_in_or_equal($filters['companies'], SQL_PARAMS_NAMED, 'companynameoption');
            $where[] = "department {$insql}";
        }

        $sql = "SELECT DISTINCT department
                  FROM {user}
                 WHERE " . implode(' AND ', $where) . "
              ORDER BY department ASC";

        return $this->text_options($DB->get_fieldset_sql($sql, $params));
    }

    public function company_filter_key(): string {
        return $this->has_iomad_tables() ? 'companyids' : 'companies';
    }

    public function scope_filters_for_user(int $userid): array {
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

            return ['companyids' => array_values(array_unique($companyids))];
        }

        $department = (string)$DB->get_field('user', 'department', ['id' => $userid], IGNORE_MISSING);
        $department = trim($department);
        return $department === '' ? [] : ['companies' => [$department]];
    }

    public function company_name_sql(string $useralias, string $prefix): array {
        if ($this->has_iomad_tables()) {
            $companyuseralias = 'cu' . preg_replace('/[^a-z0-9]/i', '', $prefix);
            $companyalias = 'co' . preg_replace('/[^a-z0-9]/i', '', $prefix);
            return [
                'join' => "LEFT JOIN {company_users} {$companyuseralias} ON {$companyuseralias}.userid = {$useralias}.id
                           LEFT JOIN {company} {$companyalias} ON {$companyalias}.id = {$companyuseralias}.companyid",
                'expr' => "{$companyalias}.name",
                'idexpr' => "{$companyalias}.id",
                'select' => "{$companyalias}.name AS companyname",
            ];
        }

        return [
            'join' => '',
            'expr' => "{$useralias}.department",
            'idexpr' => 'NULL',
            'select' => "{$useralias}.department AS companyname",
        ];
    }

    public function append_user_company_filter(array &$where, array &$params, array $filters, string $useralias, string $prefix): void {
        global $DB;

        if ($this->has_iomad_tables() && !empty($filters['companyids'])) {
            [$insql, $inparams] = $DB->get_in_or_equal($filters['companyids'], SQL_PARAMS_NAMED, $prefix . 'company');
            $companyuseralias = 'cuf' . preg_replace('/[^a-z0-9]/i', '', $prefix);
            $where[] = "EXISTS (
                            SELECT 1
                              FROM {company_users} {$companyuseralias}
                             WHERE {$companyuseralias}.userid = {$useralias}.id
                               AND {$companyuseralias}.companyid {$insql}
                         )";
            $params += $inparams;
            return;
        }

        if (!empty($filters['companies'])) {
            [$insql, $inparams] = $DB->get_in_or_equal($filters['companies'], SQL_PARAMS_NAMED, $prefix . 'companyname');
            $where[] = "{$useralias}.department {$insql}";
            $params += $inparams;
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
                    ['key' => 'validusers', 'value' => (string)$summary['validusers']],
                    ['key' => 'compliance', 'value' => $compliance . '%'],
                    ['key' => 'status', 'value' => $status],
                    ['key' => 'action', 'value' => 'View full report'],
                ],
            ];
        }

        return [
            'columns' => [
                ['key' => 'company', 'label' => 'Company'],
                ['key' => 'activeusers', 'label' => 'Active users'],
                ['key' => 'validusers', 'label' => 'Valid signed users'],
                ['key' => 'compliance', 'label' => 'Compliance %'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'action', 'label' => 'Action'],
            ],
            'rows' => $rows,
            'totalcount' => count($rows),
            'notice' => '',
            'description' => 'Compliance % = active users with at least one valid signed NCASign document / total active users x 100. Valid means origin=course_completion, status completed_manual or completed_auto, and expirydate or calculated expiry is not expired.',
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
                'meta' => $summary['validusers'] . ' / ' . $summary['totalactiveusers'] . ' valid users',
            ];
        }

        usort($items, static function(array $a, array $b): int {
            return $a['percent'] <=> $b['percent'];
        });

        return $items;
    }

    private function filters_for_company(array $filters, string $companyname): array {
        if ($this->has_iomad_tables()) {
            return $filters;
        }

        $filters['companies'] = [$companyname];
        return $filters;
    }

    private function text_options(array $values): array {
        $options = [];
        foreach ($values as $value) {
            $value = trim((string)$value);
            if ($value !== '') {
                $options[] = ['value' => $value, 'label' => $value];
            }
        }
        return $options;
    }

    private function table_exists(string $tablename): bool {
        global $CFG, $DB;

        require_once($CFG->libdir . '/xmldb/xmldb_table.php');
        return $DB->get_manager()->table_exists(new \xmldb_table($tablename));
    }
}
