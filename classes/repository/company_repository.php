<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

defined('MOODLE_INTERNAL') || die();

class company_repository {

    public function has_iomad_tables(): bool {
        return $this->table_exists('company') && $this->table_exists('company_users');
    }

    public function get_company_options(): array {
        global $DB;

        if ($this->has_iomad_tables()) {
            $records = $DB->get_records('company', null, 'name ASC', 'id, name', 0, 500);
            $options = [];
            foreach ($records as $record) {
                $options[] = ['value' => (string)$record->id, 'label' => format_string($record->name)];
            }
            return $options;
        }

        $sql = "SELECT DISTINCT department
                  FROM {user}
                 WHERE deleted = 0
                   AND department <> ''
              ORDER BY department ASC";

        return $this->text_options($DB->get_fieldset_sql($sql));
    }

    public function company_filter_key(): string {
        return $this->has_iomad_tables() ? 'companyids' : 'companies';
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
            $counts = $documents->status_counts($companyfilters);
            $compliance = $counts['total'] > 0 ? round(($counts['active'] / $counts['total']) * 100) : 0;
            $status = $this->compliance_status($compliance);

            $rows[] = [
                'cells' => [
                    ['key' => 'company', 'value' => (string)$record->companyname],
                    ['key' => 'activeusers', 'value' => (string)(int)$record->activeusers],
                    ['key' => 'compliance', 'value' => $counts['total'] > 0 ? $compliance . '%' : 'No documents'],
                    ['key' => 'status', 'value' => $status],
                    ['key' => 'action', 'value' => 'View full report'],
                ],
            ];
        }

        return [
            'columns' => [
                ['key' => 'company', 'label' => 'Company'],
                ['key' => 'activeusers', 'label' => 'Active users'],
                ['key' => 'compliance', 'label' => 'Compliance %'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'action', 'label' => 'Action'],
            ],
            'rows' => $rows,
            'totalcount' => count($rows),
            'notice' => '',
            'description' => 'Count of all Moodle users where deleted=0, suspended=0, grouped by company. Each row is a company aggregate; no individual names are exposed in this view.',
        ];
    }

    private function filters_for_company(array $filters, string $companyname): array {
        if ($this->has_iomad_tables()) {
            return $filters;
        }

        $filters['companies'] = [$companyname];
        return $filters;
    }

    private function compliance_status(int $compliance): string {
        if ($compliance >= 80) {
            return 'Green';
        }

        if ($compliance >= 70) {
            return 'Amber';
        }

        return 'Red';
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

        require_once($CFG->libdir . '/ddl/xmldb_table.php');
        return $DB->get_manager()->table_exists(new \xmldb_table($tablename));
    }
}
