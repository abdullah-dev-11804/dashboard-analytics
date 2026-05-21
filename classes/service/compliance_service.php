<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\service;

use block_dashboardanalytics\repository\company_repository;
use block_dashboardanalytics\repository\document_repository;
use block_dashboardanalytics\repository\eds_repository;
use block_dashboardanalytics\repository\employee_repository;
use block_dashboardanalytics\repository\server_repository;

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

        if ($drilldownkey === 'owner_total_active_users') {
            $companies = new company_repository();
            return $this->result('Total active users - company aggregate (no individual names)', $companies->active_user_aggregate($filters));
        }

        if ($drilldownkey === 'owner_eds_queue') {
            $eds = new eds_repository();
            return $this->result('EDS Queue - pending manual signatures', $eds->pending_manual_rows($filters, $page, $perpage));
        }

        if ($drilldownkey === 'owner_server_disk') {
            $server = new server_repository();
            return $this->result('Server disk', $server->disk_rows());
        }

        if ($drilldownkey === 'total_staff') {
            $employees = new employee_repository();
            $result = $employees->get_staff_rows($filters, $page, $perpage, $showidentity);
            return $this->result('Total staff', $result);
        }

        $status = '';
        if ($drilldownkey === 'expired_documents' || $drilldownkey === 'owner_expired_documents') {
            $status = 'expired';
        } else if ($drilldownkey === 'expiring_documents' || $drilldownkey === 'owner_expiring_documents') {
            $status = 'expiring';
        } else if (!empty($filters['status'])) {
            $status = $filters['status'];
        }

        $documents = new document_repository();
        $result = $documents->document_rows($filters, $status, $page, $perpage, $showidentity);

        $title = $drilldownkey === 'owner_compliance'
            ? 'Compliance - document status by employee'
            : get_string('complianceactiontable', 'block_dashboardanalytics');

        return $this->result($title, $result);
    }

    private function result(string $title, array $result): array {
        return [
            'title' => $title,
            'columns' => $result['columns'] ?? [],
            'rows' => $result['rows'] ?? [],
            'totalcount' => (int)($result['totalcount'] ?? 0),
            'notice' => (string)($result['notice'] ?? ''),
            'description' => (string)($result['description'] ?? ''),
        ];
    }
}
