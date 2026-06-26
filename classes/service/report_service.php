<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\service;

use block_dashboardanalytics\repository\report_repository;

defined('MOODLE_INTERNAL') || die();

class report_service {

    private report_repository $repository;

    public function __construct() {
        $this->repository = new report_repository();
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