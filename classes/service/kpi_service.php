<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\service;

use block_dashboardanalytics\permissions;
use block_dashboardanalytics\repository\document_repository;
use block_dashboardanalytics\repository\eds_repository;
use block_dashboardanalytics\repository\employee_repository;
use block_dashboardanalytics\repository\overview_repository;

defined('MOODLE_INTERNAL') || die();

class kpi_service {

    public function cards(array $filters, string $dashboardkey, int $userid = 0): array {
        $employees = new employee_repository();
        $documents = new document_repository();
        $eds = new eds_repository();
        $overview = new overview_repository();

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
            $currentreport = $overview->overall_employee_compliance_summary($filters);
            $previousmonth = (new \DateTimeImmutable('last day of previous month 23:59:59', new \DateTimeZone('Asia/Almaty')))->getTimestamp();
            $previousreport = $overview->overall_employee_compliance_summary($filters, $previousmonth);
            $statuscounts = $overview->status_counts($filters);

            $compliancevalue = $currentreport['total'] > 0 ? $currentreport['percent'] . '%' : 'No enrolled staff';
            $compliancestatus = $currentreport['total'] > 0
                ? ($currentreport['percent'] >= 80 ? 'ok' : ($currentreport['percent'] >= 70 ? 'warning' : 'danger'))
                : 'muted';
            $compliancetrend = $this->percent_delta_badge((float)$currentreport['percent'], (float)$previousreport['percent'], 'vs last mo');

            return [
                [
                    'key' => 'totalactiveusers',
                    'label' => 'Total active staff',
                    'value' => (string)$totalstaff,
                    'unit' => '',
                    'status' => 'info',
                    'trend' => '',
                    'drilldownkey' => 'company_total_active_users',
                    'help' => 'by dept · location · position',
                ],
                [
                    'key' => 'compliance',
                    'label' => 'Company compliance',
                    'value' => $compliancevalue,
                    'unit' => '',
                    'status' => $compliancestatus,
                    'trend' => $compliancetrend,
                    'drilldownkey' => 'company_compliance',
                    'help' => 'by dept · location · course',
                ],
                [
                    'key' => 'expiring30',
                    'label' => 'Expiring <30 days',
                    'value' => (string)$statuscounts['expiring'],
                    'unit' => '',
                    'status' => $statuscounts['expiring'] > 0 ? 'warning' : 'ok',
                    'trend' => '',
                    'drilldownkey' => 'company_expiring_documents',
                    'help' => 'employee list',
                ],
                [
                    'key' => 'expired',
                    'label' => 'Expired now',
                    'value' => (string)$statuscounts['expired'],
                    'unit' => '',
                    'status' => $statuscounts['expired'] > 0 ? 'danger' : 'ok',
                    'trend' => '',
                    'drilldownkey' => 'company_expired_documents',
                    'help' => 'urgent action list',
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

    private function percent_delta_badge(float $current, float $previous, string $suffix): string {
        $delta = round($current - $previous, 1);
        if (abs($delta) < 0.1) {
            return 'flat · ' . $suffix;
        }

        return ($delta > 0 ? 'up ' : 'down ') . abs($delta) . '% ' . $suffix;
    }
}
