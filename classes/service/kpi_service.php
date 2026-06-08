<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\service;

use block_dashboardanalytics\permissions;
use block_dashboardanalytics\repository\document_repository;
use block_dashboardanalytics\repository\eds_repository;
use block_dashboardanalytics\repository\employee_repository;

defined('MOODLE_INTERNAL') || die();

class kpi_service {

    public function cards(array $filters, string $dashboardkey, int $userid = 0): array {
        $employees = new employee_repository();
        $documents = new document_repository();
        $eds = new eds_repository();

        $totalstaff = $employees->count_active_users($filters);
        $documentcounts = $documents->status_counts($filters);
        $compliancesummary = $documents->compliance_summary($filters);
        $edsqueue = $eds->count_pending_manual($filters);

        $compliancevalue = $compliancesummary['compliance'] . '%';
        $compliancestatus = 'muted';
        if ($compliancesummary['configured']) {
            $compliance = $compliancesummary['compliance'];
            $compliancestatus = $compliance >= 80 ? 'ok' : ($compliance >= 70 ? 'warning' : 'danger');
        }

        if ($dashboardkey === permissions::DASHBOARD_EMPLOYEE) {
            return [
                [
                    'key' => 'personalstatus',
                    'label' => 'My training status',
                    'value' => $documentcounts['configured'] ? $compliancevalue : 'Pending',
                    'unit' => '',
                    'status' => $documentcounts['configured'] ? $compliancestatus : 'muted',
                    'trend' => $documentcounts['configured'] ? 'Personal scope' : 'Data pending',
                    'drilldownkey' => 'employee_documents',
                    'help' => 'Personal document status for the logged-in employee. Expiry details depend on the document validity milestone.',
                ],
                [
                    'key' => 'certificates',
                    'label' => 'Certificates',
                    'value' => $documentcounts['configured'] ? (string)$documentcounts['total'] : 'Pending',
                    'unit' => '',
                    'status' => $documentcounts['configured'] ? 'ok' : 'muted',
                    'trend' => 'My documents',
                    'drilldownkey' => 'employee_documents',
                    'help' => 'Signed certificate/protocol rows linked to this employee.',
                ],
                [
                    'key' => 'expiring30',
                    'label' => get_string('kpi:expiring30', 'block_dashboardanalytics'),
                    'value' => $documentcounts['configured'] ? (string)$documentcounts['expiring'] : 'Pending',
                    'unit' => '',
                    'status' => $documentcounts['expiring'] > 0 ? 'warning' : ($documentcounts['configured'] ? 'ok' : 'muted'),
                    'trend' => $documentcounts['configured'] ? 'Next 30 days' : 'Data pending',
                    'drilldownkey' => 'employee_documents',
                    'help' => 'Personal documents expiring within 30 days once validity data is available.',
                ],
                [
                    'key' => 'courses',
                    'label' => 'Courses',
                    'value' => 'Pending',
                    'unit' => '',
                    'status' => 'muted',
                    'trend' => 'LMS integration',
                    'drilldownkey' => 'employee_courses',
                    'help' => 'Required/current course list will be connected during the employee dashboard integration slice.',
                ],
            ];
        }

        if ($dashboardkey === permissions::DASHBOARD_COMPANY) {
            return [
                [
                    'key' => 'totalactiveusers',
                    'label' => 'Total active users',
                    'value' => (string)$totalstaff,
                    'unit' => '',
                    'status' => 'ok',
                    'trend' => 'Company aggregate',
                    'drilldownkey' => 'company_total_active_users',
                    'help' => 'Company aggregate of active Moodle users where deleted=0 and suspended=0. No individual names are shown.',
                ],
                [
                    'key' => 'compliance',
                    'label' => get_string('kpi:compliance', 'block_dashboardanalytics'),
                    'value' => $compliancevalue,
                    'unit' => '',
                    'status' => $compliancestatus,
                    'trend' => $compliancesummary['validusers'] . ' / ' . $compliancesummary['totalactiveusers'] . ' users',
                    'drilldownkey' => 'company_compliance',
                    'help' => 'Compliance = active users with at least one valid signed NCASign document divided by total active users.',
                ],
                [
                    'key' => 'expiring30',
                    'label' => get_string('kpi:expiring30', 'block_dashboardanalytics'),
                    'value' => $documentcounts['configured'] ? (string)$documentcounts['expiring'] : 'Pending',
                    'unit' => '',
                    'status' => $documentcounts['configured'] ? ($documentcounts['expiring'] > 0 ? 'warning' : 'ok') : 'muted',
                    'trend' => $documentcounts['configured'] ? 'Next 30 days' : 'Data pending',
                    'drilldownkey' => 'company_expiring_documents',
                    'help' => 'NCASign documents with expirydate in the next 30 days where origin is not demo_job.',
                ],
                [
                    'key' => 'expired',
                    'label' => get_string('kpi:expired', 'block_dashboardanalytics'),
                    'value' => $documentcounts['configured'] ? (string)$documentcounts['expired'] : 'Pending',
                    'unit' => '',
                    'status' => $documentcounts['configured'] ? ($documentcounts['expired'] > 0 ? 'danger' : 'ok') : 'muted',
                    'trend' => $documentcounts['configured'] ? 'Past expiry date' : 'Data pending',
                    'drilldownkey' => 'company_expired_documents',
                    'help' => 'NCASign documents with expirydate in the past where origin is not demo_job.',
                ],
                [
                    'key' => 'edsqueue',
                    'label' => 'EDS Queue',
                    'value' => (string)$edsqueue,
                    'unit' => '',
                    'status' => $edsqueue > 0 ? 'warning' : 'ok',
                    'trend' => 'Pending manual',
                    'drilldownkey' => 'company_eds_queue',
                    'help' => 'Pending manual EDS signatures from NCASign, using the current expected signer only.',
                ],
                [
                    'key' => 'acwavr',
                    'label' => 'ACW/AVR report',
                    'value' => 'Pending',
                    'unit' => '',
                    'status' => 'muted',
                    'trend' => 'Excel export',
                    'drilldownkey' => 'company_compliance',
                    'help' => 'Act of Completed Works report export will sync course completion counts from Moodle for the selected company and month.',
                ],
            ];
        }

        return [
            [
                'key' => 'totalstaff',
                'label' => get_string('kpi:totalstaff', 'block_dashboardanalytics'),
                'value' => (string)$totalstaff,
                'unit' => '',
                'status' => 'ok',
                'trend' => '',
                'drilldownkey' => 'client_total_staff',
                'help' => 'Active confirmed Moodle users in the current filter scope.',
            ],
            [
                'key' => 'compliance',
                'label' => get_string('kpi:compliance', 'block_dashboardanalytics'),
                'value' => $compliancevalue,
                'unit' => '',
                'status' => $compliancestatus,
                'trend' => '',
                'drilldownkey' => 'client_compliance',
                'help' => $documentcounts['configured']
                    ? 'Percentage of configured document rows that are active and not expiring within 30 days.'
                    : 'Configure the certificate/document table in block settings to calculate compliance.',
            ],
            [
                'key' => 'expiring30',
                'label' => get_string('kpi:expiring30', 'block_dashboardanalytics'),
                'value' => $documentcounts['configured'] ? (string)$documentcounts['expiring'] : 'Pending',
                'unit' => '',
                'status' => $documentcounts['configured'] ? ($documentcounts['expiring'] > 0 ? 'warning' : 'ok') : 'muted',
                'trend' => $documentcounts['configured'] ? '' : 'Data pending',
                'drilldownkey' => 'client_expiring_documents',
                'help' => 'Documents expiring in the next 30 days.',
            ],
            [
                'key' => 'expired',
                'label' => get_string('kpi:expired', 'block_dashboardanalytics'),
                'value' => $documentcounts['configured'] ? (string)$documentcounts['expired'] : 'Pending',
                'unit' => '',
                'status' => $documentcounts['configured'] ? ($documentcounts['expired'] > 0 ? 'danger' : 'ok') : 'muted',
                'trend' => $documentcounts['configured'] ? '' : 'Data pending',
                'drilldownkey' => 'client_expired_documents',
                'help' => 'Documents whose configured expiry timestamp is already in the past.',
            ],
            [
                'key' => 'edsqueue',
                'label' => 'EDS Queue',
                'value' => (string)$edsqueue,
                'unit' => '',
                'status' => $edsqueue > 0 ? 'warning' : 'ok',
                'trend' => 'Pending manual',
                'drilldownkey' => 'client_eds_queue',
                'help' => 'Pending manual EDS signatures from NCASign for the current client scope.',
            ],
            [
                'key' => 'documentsource',
                'label' => get_string('kpi:documentsource', 'block_dashboardanalytics'),
                'value' => $documentcounts['configured'] ? 'Ready' : 'Pending',
                'unit' => '',
                'status' => $documentcounts['configured'] ? 'ok' : 'muted',
                'trend' => '',
                'drilldownkey' => 'client_compliance',
                'help' => 'Status of the configured compliance document source.',
            ],
        ];
    }
}
