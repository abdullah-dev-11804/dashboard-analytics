<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\service;

use block_dashboardanalytics\repository\completion_repository;
use block_dashboardanalytics\repository\document_repository;
use block_dashboardanalytics\repository\employee_repository;

defined('MOODLE_INTERNAL') || die();

class kpi_service {

    public function cards(array $filters, string $dashboardkey): array {
        global $DB;

        $employees = new employee_repository();
        $documents = new document_repository();
        $completions = new completion_repository();

        $totalstaff = $employees->count_active_users($filters);
        $documentcounts = $documents->status_counts($filters);
        $activecourses = (int)$DB->count_records_select('course', 'id <> :siteid AND visible = 1', ['siteid' => SITEID]);
        $completioncount = $completions->count_completed_courses($filters);

        $compliancevalue = 'Set up';
        $compliancestatus = 'muted';
        if ($documentcounts['configured'] && $documentcounts['total'] > 0) {
            $compliance = round(($documentcounts['active'] / $documentcounts['total']) * 100);
            $compliancevalue = $compliance . '%';
            $compliancestatus = $compliance >= 80 ? 'ok' : ($compliance >= 70 ? 'warning' : 'danger');
        }

        return [
            [
                'key' => 'totalstaff',
                'label' => get_string('kpi:totalstaff', 'block_dashboardanalytics'),
                'value' => (string)$totalstaff,
                'unit' => '',
                'status' => 'ok',
                'trend' => '',
                'drilldownkey' => 'total_staff',
                'help' => 'Active confirmed Moodle users in the current filter scope.',
            ],
            [
                'key' => 'compliance',
                'label' => get_string('kpi:compliance', 'block_dashboardanalytics'),
                'value' => $compliancevalue,
                'unit' => '',
                'status' => $compliancestatus,
                'trend' => '',
                'drilldownkey' => 'compliance_action_table',
                'help' => $documentcounts['configured']
                    ? 'Percentage of configured document rows that are active and not expiring within 30 days.'
                    : 'Configure the certificate/document table in block settings to calculate compliance.',
            ],
            [
                'key' => 'expiring30',
                'label' => get_string('kpi:expiring30', 'block_dashboardanalytics'),
                'value' => (string)$documentcounts['expiring'],
                'unit' => '',
                'status' => $documentcounts['expiring'] > 0 ? 'warning' : 'ok',
                'trend' => '',
                'drilldownkey' => 'expiring_documents',
                'help' => 'Documents expiring in the next 30 days.',
            ],
            [
                'key' => 'expired',
                'label' => get_string('kpi:expired', 'block_dashboardanalytics'),
                'value' => (string)$documentcounts['expired'],
                'unit' => '',
                'status' => $documentcounts['expired'] > 0 ? 'danger' : 'ok',
                'trend' => '',
                'drilldownkey' => 'expired_documents',
                'help' => 'Documents whose configured expiry timestamp is already in the past.',
            ],
            [
                'key' => 'activecourses',
                'label' => get_string('kpi:activecourses', 'block_dashboardanalytics'),
                'value' => (string)$activecourses,
                'unit' => '',
                'status' => 'ok',
                'trend' => '',
                'drilldownkey' => 'courses',
                'help' => 'Visible Moodle courses, excluding the site front page course.',
            ],
            [
                'key' => 'documentsource',
                'label' => get_string('kpi:documentsource', 'block_dashboardanalytics'),
                'value' => $documentcounts['configured'] ? 'Ready' : 'Pending',
                'unit' => '',
                'status' => $documentcounts['configured'] ? 'ok' : 'muted',
                'trend' => $completioncount > 0 ? $completioncount . ' completions' : '',
                'drilldownkey' => 'compliance_action_table',
                'help' => 'Status of the configured compliance document source.',
            ],
        ];
    }
}

