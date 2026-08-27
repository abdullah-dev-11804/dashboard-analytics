<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\service;

use block_dashboardanalytics\permissions;
use block_dashboardanalytics\repository\company_repository;
use block_dashboardanalytics\repository\report_repository;

defined('MOODLE_INTERNAL') || die();

class report_service {

    private report_repository $repository;

    public function __construct() {
        $this->repository = new report_repository();
    }

    public function builder_config(\context $context, int $userid): array {
        if (!is_siteadmin($userid)) {
            throw new \moodle_exception('error:noaccess', 'block_dashboardanalytics');
        }

        $now = time();
        $currentyear = (int)date('Y', $now);
        $currentmonth = (int)date('n', $now);

        $months = [];
        for ($month = 1; $month <= 12; $month++) {
            $months[] = [
                'value' => (string)$month,
                'label' => userdate(make_timestamp(2024, $month, 1), '%B'),
            ];
        }

        $years = [];
        for ($year = $currentyear - 3; $year <= $currentyear + 1; $year++) {
            $years[] = [
                'value' => (string)$year,
                'label' => (string)$year,
            ];
        }

        $companyselector = is_siteadmin($userid);
        $companies = [];
        if ($companyselector) {
            $companies = $this->repository->company_options();
        } else {
            $companyrepo = new company_repository();
            $scope = $companyrepo->scope_details_for_user($userid);
            $companies = $companyrepo->get_company_options(['allowedcompanyids' => $scope['companyids'] ?? []]);
        }

        return [
            'companyselector' => $companyselector,
            'companies' => $companies,
            'columns' => $this->repository->available_columns(),
            'defaultcolumns' => $this->repository->default_column_keys(),
            'templates' => $this->repository->templates_for_user($userid),
            'months' => $months,
            'years' => $years,
            'defaultmonth' => $currentmonth,
            'defaultyear' => $currentyear,
            'defaulttemplate' => get_string('reportsbuilder:untitledtemplate', 'block_dashboardanalytics'),
        ];
    }

    public function builder_rows(
        array $filters,
        array $options,
        int $page,
        int $perpage,
        string $sortkey,
        string $sortdir
    ): array {
        return $this->repository->report_rows($filters, $options, $page, $perpage, $sortkey, $sortdir);
    }

    public function save_template(int $userid, int $templateid, string $name, array $columns, array $filters): array {
        if (!is_siteadmin($userid)) {
            throw new \moodle_exception('error:noaccess', 'block_dashboardanalytics');
        }
        return $this->repository->save_template($userid, $templateid, $name, $columns, $filters);
    }

    public function delete_template(int $userid, int $templateid): array {
        if (!is_siteadmin($userid)) {
            throw new \moodle_exception('error:noaccess', 'block_dashboardanalytics');
        }
        $this->repository->delete_template($userid, $templateid);
        return ['success' => true, 'templates' => $this->repository->templates_for_user($userid)];
    }

    public function config(): array {
        $now = time();
        $currentyear = (int)date('Y', $now);
        $currentmonth = (int)date('n', $now);

        $months = [];
        for ($month = 1; $month <= 12; $month++) {
            $months[] = [
                'value' => (string)$month,
                'label' => userdate(make_timestamp(2024, $month, 1), '%B'),
            ];
        }

        $years = [];
        for ($year = $currentyear - 3; $year <= $currentyear + 1; $year++) {
            $years[] = [
                'value' => (string)$year,
                'label' => (string)$year,
            ];
        }

        return [
            'companies' => $this->repository->company_options(),
            'months' => $months,
            'years' => $years,
            'defaultmonth' => $currentmonth,
            'defaultyear' => $currentyear,
            'defaultprovider' => 'TOO "SENTAL"',
        ];
    }

    public function load_services(int $companyid, int $month, int $year): array {
        $rows = $this->repository->act_service_rows($companyid, $month, $year);

        $lmstotal = 0;
        $acttotal = 0;

        foreach ($rows as $row) {
            $lmstotal += (int)$row['lmscount'];
            $acttotal += (int)$row['actqty'];
        }

        return [
            'companyname' => $this->repository->company_name($companyid),
            'rows' => $rows,
            'lmstotal' => $lmstotal,
            'acttotal' => $acttotal,
            'difference' => $acttotal - $lmstotal,
        ];
    }
}
