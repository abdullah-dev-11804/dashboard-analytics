<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\service;

use block_dashboardanalytics\permissions;
use block_dashboardanalytics\repository\company_repository;
use block_dashboardanalytics\repository\document_repository;
use block_dashboardanalytics\repository\eds_repository;
use block_dashboardanalytics\repository\employee_repository;

defined('MOODLE_INTERNAL') || die();

class compliance_service {

    public function drilldown(
        string $dashboardkey,
        string $drilldownkey,
        array $filters,
        int $page,
        int $perpage,
        bool $showidentity,
        int $userid
    ): array {
        $page = max(0, $page);
        $perpage = min(100, max(10, $perpage));

        if (!$this->is_allowed_drilldown($dashboardkey, $drilldownkey)) {
            throw new \moodle_exception('error:noaccess', 'block_dashboardanalytics');
        }

        if ($drilldownkey === 'company_total_active_users') {
            $companies = new company_repository();
            return $this->result(get_string('drilldown:title:totalactiveusers', 'block_dashboardanalytics'), $companies->active_user_aggregate($filters));
        }

        if ($drilldownkey === 'company_eds_queue' || $drilldownkey === 'client_eds_queue') {
            $eds = new eds_repository();
            return $this->result(get_string('drilldown:title:edsqueue', 'block_dashboardanalytics'), $eds->pending_manual_rows($filters, $page, $perpage));
        }

        if ($drilldownkey === 'client_total_staff') {
            $employees = new employee_repository();
            $result = $employees->get_staff_rows($filters, $page, $perpage, $showidentity);
            return $this->result(get_string('drilldown:title:totalstaff', 'block_dashboardanalytics'), $result);
        }

        if ($drilldownkey === 'employee_courses') {
            return $this->result(get_string('drilldown:title:mycourses', 'block_dashboardanalytics'), [
                'columns' => [],
                'rows' => [],
                'totalcount' => 0,
                'notice' => get_string('drilldown:notice:coursespending', 'block_dashboardanalytics'),
            ]);
        }

        $status = '';
        if ($drilldownkey === 'company_expired_documents' || $drilldownkey === 'client_expired_documents') {
            $status = 'expired';
        } else if ($drilldownkey === 'company_expiring_documents' || $drilldownkey === 'client_expiring_documents') {
            $status = 'expiring';
        } else if (!empty($filters['status'])) {
            $status = $filters['status'];
        }

        $documents = new document_repository();
        $result = $documents->document_rows($filters, $status, $page, $perpage, $showidentity);

        $title = $drilldownkey === 'employee_documents'
            ? get_string('drilldown:title:mydocuments', 'block_dashboardanalytics')
            : ($drilldownkey === 'company_compliance' || $drilldownkey === 'client_compliance'
            ? get_string('drilldown:title:compliance', 'block_dashboardanalytics')
            : get_string('complianceactiontable', 'block_dashboardanalytics'));

        return $this->result($title, $result);
    }

    private function is_allowed_drilldown(string $dashboardkey, string $drilldownkey): bool {
        $allowed = [
            permissions::DASHBOARD_COMPANY => [
                'company_total_active_users',
                'company_compliance',
                'company_expiring_documents',
                'company_expired_documents',
                'company_eds_queue',
            ],
            permissions::DASHBOARD_CLIENT => [
                'client_total_staff',
                'client_compliance',
                'client_expiring_documents',
                'client_expired_documents',
                'client_eds_queue',
            ],
            permissions::DASHBOARD_EMPLOYEE => [
                'employee_documents',
                'employee_courses',
            ],
        ];

        return in_array($drilldownkey, $allowed[$dashboardkey] ?? [], true);
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
