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
                    'label' => get_string('kpi:mytrainingstatus', 'block_dashboardanalytics'),
                    'value' => $documentcounts['configured'] ? $compliancevalue : get_string('kpi:value:pending', 'block_dashboardanalytics'),
                    'unit' => '',
                    'status' => $documentcounts['configured'] ? $compliancestatus : 'muted',
                    'trend' => $documentcounts['configured'] ? get_string('kpi:help:scopepersonal', 'block_dashboardanalytics') : get_string('kpi:help:datapending', 'block_dashboardanalytics'),
                    'drilldownkey' => 'employee_documents',
                    'help' => get_string('kpi:help:personalstatus', 'block_dashboardanalytics'),
                ],
                [
                    'key' => 'certificates',
                    'label' => get_string('kpi:certificates', 'block_dashboardanalytics'),
                    'value' => $documentcounts['configured'] ? (string)$documentcounts['total'] : get_string('kpi:value:pending', 'block_dashboardanalytics'),
                    'unit' => '',
                    'status' => $documentcounts['configured'] ? 'ok' : 'muted',
                    'trend' => get_string('kpi:help:mydocuments', 'block_dashboardanalytics'),
                    'drilldownkey' => 'employee_documents',
                    'help' => get_string('kpi:help:personaldocuments', 'block_dashboardanalytics'),
                ],
                [
                    'key' => 'expiring30',
                    'label' => get_string('kpi:expiring30', 'block_dashboardanalytics'),
                    'value' => $documentcounts['configured'] ? (string)$documentcounts['expiring'] : get_string('kpi:value:pending', 'block_dashboardanalytics'),
                    'unit' => '',
                    'status' => $documentcounts['expiring'] > 0 ? 'warning' : ($documentcounts['configured'] ? 'ok' : 'muted'),
                    'trend' => $documentcounts['configured'] ? get_string('kpi:help:next30days', 'block_dashboardanalytics') : get_string('kpi:help:datapending', 'block_dashboardanalytics'),
                    'drilldownkey' => 'employee_documents',
                    'help' => get_string('kpi:help:personaldocuments30', 'block_dashboardanalytics'),
                ],
                [
                    'key' => 'courses',
                    'label' => get_string('kpi:courses', 'block_dashboardanalytics'),
                    'value' => get_string('kpi:value:pending', 'block_dashboardanalytics'),
                    'unit' => '',
                    'status' => 'muted',
                    'trend' => get_string('kpi:help:lmsintegration', 'block_dashboardanalytics'),
                    'drilldownkey' => 'employee_courses',
                    'help' => get_string('kpi:help:personalcourses', 'block_dashboardanalytics'),
                ],
            ];
        }

        if ($dashboardkey === permissions::DASHBOARD_COMPANY) {
            $currentreport = $overview->overall_employee_compliance_summary($filters);
            $previousmonth = (new \DateTimeImmutable('last day of previous month 23:59:59', new \DateTimeZone('Asia/Almaty')))->getTimestamp();
            $previousreport = $overview->overall_employee_compliance_summary($filters, $previousmonth);
            $statuscounts = $overview->status_counts($filters);

            $compliancevalue = $currentreport['total'] > 0 ? $currentreport['percent'] . '%' : get_string('kpi:value:nostaff', 'block_dashboardanalytics');
            $compliancestatus = $currentreport['total'] > 0
                ? ($currentreport['percent'] >= 80 ? 'ok' : ($currentreport['percent'] >= 70 ? 'warning' : 'danger'))
                : 'muted';
            $compliancetrend = $this->percent_delta_badge((float)$currentreport['percent'], (float)$previousreport['percent'], get_string('kpi:trend:vslastmo', 'block_dashboardanalytics'));

            return [
                [
                    'key' => 'totalactiveusers',
                    'label' => get_string('kpi:totalstaff', 'block_dashboardanalytics'),
                    'value' => (string)$totalstaff,
                    'unit' => '',
                    'status' => 'info',
                    'trend' => '',
                    'drilldownkey' => 'company_total_active_users',
                    'help' => get_string('kpi:help:bydeptlocationposition', 'block_dashboardanalytics'),
                ],
                [
                    'key' => 'compliance',
                    'label' => get_string('kpi:companycompliance', 'block_dashboardanalytics'),
                    'value' => $compliancevalue,
                    'unit' => '',
                    'status' => $compliancestatus,
                    'trend' => $compliancetrend,
                    'drilldownkey' => 'company_compliance',
                    'help' => get_string('kpi:help:bydeptlocationcourse', 'block_dashboardanalytics'),
                ],
                [
                    'key' => 'expiring30',
                    'label' => get_string('kpi:expiring30long', 'block_dashboardanalytics'),
                    'value' => (string)$statuscounts['expiring'],
                    'unit' => '',
                    'status' => $statuscounts['expiring'] > 0 ? 'warning' : 'ok',
                    'trend' => '',
                    'drilldownkey' => 'company_expiring_documents',
                    'help' => get_string('kpi:help:employeelist', 'block_dashboardanalytics'),
                ],
                [
                    'key' => 'expired',
                    'label' => get_string('kpi:expirednow', 'block_dashboardanalytics'),
                    'value' => (string)$statuscounts['expired'],
                    'unit' => '',
                    'status' => $statuscounts['expired'] > 0 ? 'danger' : 'ok',
                    'trend' => '',
                    'drilldownkey' => 'company_expired_documents',
                    'help' => get_string('kpi:help:urgentactionlist', 'block_dashboardanalytics'),
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
                'help' => get_string('kpi:help:activeconfirmedusers', 'block_dashboardanalytics'),
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
                    ? get_string('kpi:help:clientcomplianceconfigured', 'block_dashboardanalytics')
                    : get_string('kpi:help:clientcompliancepending', 'block_dashboardanalytics'),
            ],
            [
                'key' => 'expiring30',
                'label' => get_string('kpi:expiring30', 'block_dashboardanalytics'),
                'value' => $documentcounts['configured'] ? (string)$documentcounts['expiring'] : get_string('kpi:value:pending', 'block_dashboardanalytics'),
                'unit' => '',
                'status' => $documentcounts['configured'] ? ($documentcounts['expiring'] > 0 ? 'warning' : 'ok') : 'muted',
                'trend' => $documentcounts['configured'] ? '' : get_string('kpi:help:datapending', 'block_dashboardanalytics'),
                'drilldownkey' => 'client_expiring_documents',
                'help' => get_string('kpi:help:documents30', 'block_dashboardanalytics'),
            ],
            [
                'key' => 'expired',
                'label' => get_string('kpi:expired', 'block_dashboardanalytics'),
                'value' => $documentcounts['configured'] ? (string)$documentcounts['expired'] : get_string('kpi:value:pending', 'block_dashboardanalytics'),
                'unit' => '',
                'status' => $documentcounts['configured'] ? ($documentcounts['expired'] > 0 ? 'danger' : 'ok') : 'muted',
                'trend' => $documentcounts['configured'] ? '' : get_string('kpi:help:datapending', 'block_dashboardanalytics'),
                'drilldownkey' => 'client_expired_documents',
                'help' => get_string('kpi:help:documentsexpired', 'block_dashboardanalytics'),
            ],
            [
                'key' => 'edsqueue',
                'label' => get_string('panel:edsqueue:title', 'block_dashboardanalytics'),
                'value' => (string)$edsqueue,
                'unit' => '',
                'status' => $edsqueue > 0 ? 'warning' : 'ok',
                'trend' => get_string('panel:pendingmanual', 'block_dashboardanalytics'),
                'drilldownkey' => 'client_eds_queue',
                'help' => get_string('kpi:help:edsqueue', 'block_dashboardanalytics'),
            ],
            [
                'key' => 'documentsource',
                'label' => get_string('kpi:documentsource', 'block_dashboardanalytics'),
                'value' => $documentcounts['configured'] ? get_string('kpi:documentsourceready', 'block_dashboardanalytics') : get_string('kpi:value:pending', 'block_dashboardanalytics'),
                'unit' => '',
                'status' => $documentcounts['configured'] ? 'ok' : 'muted',
                'trend' => '',
                'drilldownkey' => 'client_compliance',
                'help' => get_string('kpi:help:documentsource', 'block_dashboardanalytics'),
            ],
        ];
    }

    private function percent_delta_badge(float $current, float $previous, string $suffix): string {
        $delta = round($current - $previous, 1);
        if (abs($delta) < 0.1) {
            return get_string('kpi:trend:flat', 'block_dashboardanalytics', $suffix);
        }

        if ($delta > 0) {
            return get_string('kpi:trend:up', 'block_dashboardanalytics', (object)['delta' => abs($delta), 'suffix' => $suffix]);
        }

        return get_string('kpi:trend:down', 'block_dashboardanalytics', (object)['delta' => abs($delta), 'suffix' => $suffix]);
    }
}
