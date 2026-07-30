<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\service;

use block_dashboardanalytics\filters;
use block_dashboardanalytics\permissions;
use block_dashboardanalytics\repository\document_repository;
use block_dashboardanalytics\repository\eds_repository;
use block_dashboardanalytics\repository\employee_repository;
use block_dashboardanalytics\repository\overview_repository;
use block_dashboardanalytics\repository\server_repository;

defined('MOODLE_INTERNAL') || die();

class kpi_service {

    public function cards(array $filters, string $dashboardkey, int $userid = 0): array {
        $employees = new employee_repository();
        $documents = new document_repository();
        $eds = new eds_repository();
        $overview = new overview_repository();
        $server = new server_repository();
        $iscompanyowner = permissions::is_company_owner(\context_system::instance(), $userid);

        $totalstaff = $employees->count_active_users($filters);
        $documentcounts = $documents->status_counts($filters);
        $compliancesummary = $documents->compliance_summary($filters);
        $edsqueue = $eds->queue_summary($filters);
        $thresholds = filters::compliance_thresholds($filters);

        $compliancevalue = $compliancesummary['compliance'] . '%';
        $compliancestatus = 'muted';
        if ($compliancesummary['configured']) {
            $compliance = $compliancesummary['compliance'];
            $compliancestatus = $compliance >= $thresholds['compliant']
                ? 'ok'
                : ($compliance >= $thresholds['critical'] ? 'warning' : 'danger');
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
            $totalcheckscount = (int)$statuscounts['active'] + (int)$statuscounts['expiring'] + (int)$statuscounts['expired'] + (int)$statuscounts['nodocument'];
            $totalchecks = max(1, $totalcheckscount);
            $cards = [
                [
                    'key' => 'totalactiveusers',
                    'label' => get_string('kpi:totalactiveusers', 'block_dashboardanalytics'),
                    'value' => (string)$totalstaff,
                    'unit' => '',
                    'status' => 'info',
                    'railpercent' => 100,
                    'trend' => '',
                    'drilldownkey' => 'company_total_active_users',
                    'help' => '',
                ],
            ];

            $compliancevalue = $currentreport['total'] > 0 ? $currentreport['percent'] . '%' : get_string('kpi:value:nostaff', 'block_dashboardanalytics');
            $compliancetrend = (int)$statuscounts['active'] . ' / ' . $totalcheckscount;

            $cards[] = [
                'key' => 'overallcompliance',
                'label' => get_string('kpi:overallcompliance', 'block_dashboardanalytics'),
                'value' => $compliancevalue,
                'unit' => '',
                'status' => 'ok',
                'railpercent' => round((float)$currentreport['percent'], 1),
                'trend' => $compliancetrend,
                'trendstyle' => 'plain',
                'drilldownkey' => 'company_compliance',
                'filterstatus' => 'active',
                'note' => $currentreport['total'] > 0 && (float)$currentreport['percent'] < $thresholds['compliant']
                    ? get_string('kpi:belowthreshold', 'block_dashboardanalytics')
                    : '',
                'help' => '',
            ];
            $cards[] = [
                'key' => 'expiring30',
                'label' => get_string('kpi:expiring30long', 'block_dashboardanalytics'),
                'value' => (string)$statuscounts['expiring'],
                'unit' => '',
                'status' => $statuscounts['expiring'] > 0 ? 'warning' : 'ok',
                'railpercent' => round(((int)$statuscounts['expiring'] / $totalchecks) * 100, 1),
                'trend' => round(((int)$statuscounts['expiring'] / $totalchecks) * 100, 1) . '%',
                'trendstyle' => 'plain',
                'drilldownkey' => 'company_expiring_documents',
                'help' => '',
            ];
            $cards[] = [
                'key' => 'expired',
                'label' => get_string('kpi:expirednow', 'block_dashboardanalytics'),
                'value' => (string)$statuscounts['expired'],
                'unit' => '',
                'status' => $statuscounts['expired'] > 0 ? 'danger' : 'ok',
                'railpercent' => round(((int)$statuscounts['expired'] / $totalchecks) * 100, 1),
                'trend' => round(((int)$statuscounts['expired'] / $totalchecks) * 100, 1) . '%',
                'trendstyle' => 'plain',
                'drilldownkey' => 'company_expired_documents',
                'help' => '',
            ];
            if (!$iscompanyowner) {
                $cards[] = [
                    'key' => 'edsqueue',
                    'label' => get_string('panel:edsqueue:title', 'block_dashboardanalytics'),
                    'value' => (string)$edsqueue['count'],
                    'unit' => '',
                    'status' => $edsqueue['status'],
                    'railpercent' => $edsqueue['count'] > 0 ? 100 : 0,
                    'trend' => $edsqueue['count'] > 0 ? $edsqueue['badge'] : '',
                    'drilldownkey' => 'company_eds_queue',
                    'help' => '',
                ];
            }

            if (is_siteadmin($userid)) {
                $disk = $server->disk_card();
                $cards[] = [
                    'key' => 'serverdisk',
                    'label' => get_string('kpi:serverdisk', 'block_dashboardanalytics'),
                    'value' => (string)$disk['value'],
                    'unit' => '',
                    'status' => (string)$disk['status'],
                    'railpercent' => is_numeric(rtrim((string)$disk['value'], '%')) ? (float)rtrim((string)$disk['value'], '%') : 0,
                    'trend' => (string)$disk['trend'],
                    'drilldownkey' => 'company_server_disk',
                    'help' => get_string('kpi:help:serverdisk', 'block_dashboardanalytics'),
                ];
            }

            return $cards;
        }

        $currentreport = $overview->overall_employee_compliance_summary($filters);
        $previousmonth = (new \DateTimeImmutable('last day of previous month 23:59:59', new \DateTimeZone('Asia/Almaty')))->getTimestamp();
        $previousreport = $overview->overall_employee_compliance_summary($filters, $previousmonth);
        $statuscounts = $overview->status_counts($filters);
        $totalcheckscount = (int)$statuscounts['active'] + (int)$statuscounts['expiring'] + (int)$statuscounts['expired'] + (int)$statuscounts['nodocument'];
        $totalchecks = max(1, $totalcheckscount);
        $clientcompliancevalue = $currentreport['total'] > 0 ? $currentreport['percent'] . '%' : get_string('kpi:value:nostaff', 'block_dashboardanalytics');
        $clientcompliancetrend = (int)$statuscounts['active'] . ' / ' . $totalcheckscount;

        return [
            [
                'key' => 'totalactiveusers',
                'label' => get_string('kpi:totalactiveusers', 'block_dashboardanalytics'),
                'value' => (string)$totalstaff,
                'unit' => '',
                'status' => 'ok',
                'railpercent' => 100,
                'trend' => '',
                'drilldownkey' => 'client_total_staff',
                'help' => '',
            ],
            [
                'key' => 'overallcompliance',
                'label' => get_string('kpi:overallcompliance', 'block_dashboardanalytics'),
                'value' => $clientcompliancevalue,
                'unit' => '',
                'status' => 'ok',
                'railpercent' => round((float)$currentreport['percent'], 1),
                'trend' => $clientcompliancetrend,
                'trendstyle' => 'plain',
                'drilldownkey' => 'client_compliance',
                'filterstatus' => 'active',
                'note' => $currentreport['total'] > 0 && (float)$currentreport['percent'] < $thresholds['compliant']
                    ? get_string('kpi:belowthreshold', 'block_dashboardanalytics')
                    : '',
                'help' => '',
            ],
            [
                'key' => 'expiring30',
                'label' => get_string('kpi:expiring30long', 'block_dashboardanalytics'),
                'value' => (string)$statuscounts['expiring'],
                'unit' => '',
                'status' => $statuscounts['expiring'] > 0 ? 'warning' : 'ok',
                'railpercent' => round(((int)$statuscounts['expiring'] / $totalchecks) * 100, 1),
                'trend' => round(((int)$statuscounts['expiring'] / $totalchecks) * 100, 1) . '%',
                'trendstyle' => 'plain',
                'drilldownkey' => 'client_expiring_documents',
                'help' => '',
            ],
            [
                'key' => 'expired',
                'label' => get_string('kpi:expirednow', 'block_dashboardanalytics'),
                'value' => (string)$statuscounts['expired'],
                'unit' => '',
                'status' => $statuscounts['expired'] > 0 ? 'danger' : 'ok',
                'railpercent' => round(((int)$statuscounts['expired'] / $totalchecks) * 100, 1),
                'trend' => round(((int)$statuscounts['expired'] / $totalchecks) * 100, 1) . '%',
                'trendstyle' => 'plain',
                'drilldownkey' => 'client_expired_documents',
                'help' => '',
            ],
            [
                'key' => 'edsqueue',
                'label' => get_string('panel:edsqueue:title', 'block_dashboardanalytics'),
                'value' => (string)$edsqueue['count'],
                'unit' => '',
                'status' => $edsqueue['status'],
                'railpercent' => $edsqueue['count'] > 0 ? 100 : 0,
                'trend' => $edsqueue['count'] > 0 ? $edsqueue['badge'] : '',
                'drilldownkey' => 'client_eds_queue',
                'help' => '',
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
