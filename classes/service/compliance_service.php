<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\service;

use block_dashboardanalytics\repository\document_repository;
use block_dashboardanalytics\repository\employee_repository;

defined('MOODLE_INTERNAL') || die();

class compliance_service {

    public function drilldown(
        string $drilldownkey,
        array $filters,
        int $page,
        int $perpage,
        bool $showidentity
    ): array {
        $page = max(0, $page);
        $perpage = min(100, max(10, $perpage));

        if ($drilldownkey === 'total_staff') {
            $employees = new employee_repository();
            $result = $employees->get_staff_rows($filters, $page, $perpage, $showidentity);
            return $this->result('Total staff', $result);
        }

        $status = '';
        if ($drilldownkey === 'expired_documents') {
            $status = 'expired';
        } else if ($drilldownkey === 'expiring_documents') {
            $status = 'expiring';
        } else if (!empty($filters['status'])) {
            $status = $filters['status'];
        }

        $documents = new document_repository();
        $result = $documents->document_rows($filters, $status, $page, $perpage, $showidentity);

        return $this->result(get_string('complianceactiontable', 'block_dashboardanalytics'), $result);
    }

    private function result(string $title, array $result): array {
        return [
            'title' => $title,
            'columns' => $result['columns'] ?? [],
            'rows' => $result['rows'] ?? [],
            'totalcount' => (int)($result['totalcount'] ?? 0),
            'notice' => (string)($result['notice'] ?? ''),
        ];
    }
}

