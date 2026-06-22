<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\service;

use block_dashboardanalytics\permissions;
use block_dashboardanalytics\service\kpi_service;
use block_dashboardanalytics\repository\document_repository;
use block_dashboardanalytics\repository\eds_repository;
use block_dashboardanalytics\repository\employee_repository;
use block_dashboardanalytics\repository\overview_repository;
use block_dashboardanalytics\repository\proctoring_repository;
use block_dashboardanalytics\repository\server_repository;

defined('MOODLE_INTERNAL') || die();

class visual_service {

    public function panels(string $dashboardkey, string $tabkey, array $filters): array {
        if ($dashboardkey === permissions::DASHBOARD_CLIENT) {
            return $this->client_manager_panels($tabkey, $filters);
        }

        if ($dashboardkey === permissions::DASHBOARD_EMPLOYEE) {
            return $this->employee_panels($tabkey, $filters);
        }

        if ($dashboardkey !== permissions::DASHBOARD_COMPANY) {
            throw new \moodle_exception('error:noaccess', 'block_dashboardanalytics');
        }

        if ($tabkey === 'overview') {
            return $this->overview($filters);
        }

        if ($tabkey === 'kpis') {
            return $this->company_kpi_strip($filters);
        }

        if ($tabkey === 'compliance') {
            return $this->compliance($filters);
        }

        if ($tabkey === 'proctoring') {
            return $this->proctoring($filters);
        }

        if ($tabkey === 'forecast') {
            return $this->forecast($filters);
        }

        if ($tabkey === 'quality') {
            return $this->company_pending(
                get_string('panel:quality:title', 'block_dashboardanalytics'),
                get_string('panel:quality:pending', 'block_dashboardanalytics')
            );
        }

        if ($tabkey === 'server') {
            return $this->server($filters);
        }

        if ($tabkey === 'reports') {
            return $this->company_pending(
                get_string('panel:reports:title', 'block_dashboardanalytics'),
                get_string('panel:reports:pending', 'block_dashboardanalytics')
            );
        }

        if ($tabkey === 'turnover') {
            return $this->company_pending(
                get_string('panel:turnover:title', 'block_dashboardanalytics'),
                get_string('panel:turnover:pending', 'block_dashboardanalytics')
            );
        }

        return $this->overview($filters);
    }

    private function overview(array $filters): array {
        $overview = new overview_repository();

        return [
            'title' => get_string('panel:overview:title', 'block_dashboardanalytics'),
            'description' => get_string('panel:overview:description', 'block_dashboardanalytics'),
            'panels' => [
                $this->panel('compliancetrend', get_string('panel:compliancetrend:title', 'block_dashboardanalytics'), 'grouped', get_string('panel:compliancetrend:description', 'block_dashboardanalytics'), $overview->compliance_trend_items($filters)),
                $this->panel('companycompliance', get_string('panel:companycompliance:title', 'block_dashboardanalytics'), 'bar', get_string('panel:companycompliance:description', 'block_dashboardanalytics'), $overview->company_compliance_items($filters)),
                $this->panel('documentstatus', get_string('panel:documentstatus:title', 'block_dashboardanalytics'), 'donut', get_string('panel:documentstatus:description', 'block_dashboardanalytics'), $overview->status_distribution_items($filters)),
                $this->panel('riskcompany', get_string('panel:riskcompany:title', 'block_dashboardanalytics'), 'grouped', get_string('panel:riskcompany:description', 'block_dashboardanalytics'), $overview->expired_expiring_by_company_items($filters)),
                $this->panel('coursecompliance', get_string('panel:coursecompliance:title', 'block_dashboardanalytics'), 'bar', get_string('panel:coursecompliance:description', 'block_dashboardanalytics'), $overview->course_non_compliance_items($filters)),
            ],
        ];
    }

    private function company_kpi_strip(array $filters): array {
        $kpis = new kpi_service();
        $cards = array_map(static function(array $card): array {
            return [
                'label' => $card['label'],
                'value' => $card['value'],
                'percent' => 0.0,
                'status' => $card['status'],
                'meta' => trim(($card['trend'] !== '' ? $card['trend'] . ' · ' : '') . $card['help']),
            ];
        }, $kpis->cards($filters, permissions::DASHBOARD_COMPANY));

        return [
            'title' => get_string('panel:companykpis:title', 'block_dashboardanalytics'),
            'description' => get_string('panel:companykpis:description', 'block_dashboardanalytics'),
            'panels' => [
                $this->panel('companykpistrip', get_string('panel:companykpistrip:title', 'block_dashboardanalytics'), 'cards', get_string('panel:companykpistrip:description', 'block_dashboardanalytics'), $cards),
            ],
        ];
    }

    private function compliance(array $filters): array {
        $documents = new document_repository();
        $eds = new eds_repository();
        $edsrows = $eds->pending_manual_rows($filters, 0, 1);

        return [
            'title' => get_string('panel:compliance:title', 'block_dashboardanalytics'),
            'description' => get_string('panel:compliance:description', 'block_dashboardanalytics'),
            'panels' => [
                $this->panel('riskcompany', get_string('panel:riskcompanyshort:title', 'block_dashboardanalytics'), 'bar', get_string('panel:riskcompanyshort:description', 'block_dashboardanalytics'), $documents->risk_by_company_items($filters)),
                $this->panel('riskcourse', get_string('panel:riskcourse:title', 'block_dashboardanalytics'), 'bar', get_string('panel:riskcourse:description', 'block_dashboardanalytics'), $documents->noncompliance_by_course_items($filters)),
                $this->panel('edsqueue', get_string('panel:edsqueue:title', 'block_dashboardanalytics'), 'cards', get_string('panel:edsqueue:description', 'block_dashboardanalytics'), [[
                    'label' => get_string('panel:pendingmanual', 'block_dashboardanalytics'),
                    'value' => (string)$edsrows['totalcount'],
                    'percent' => min(100, (float)$edsrows['totalcount']),
                    'status' => $edsrows['totalcount'] > 0 ? 'warning' : 'ok',
                    'meta' => get_string('panel:pendingmanualmeta', 'block_dashboardanalytics'),
                ]]),
            ],
        ];
    }

    private function proctoring(array $filters): array {
        $proctoring = new proctoring_repository();

        if ($proctoring->has_data($filters)) {
            return [
                'title' => get_string('panel:proctoring:title', 'block_dashboardanalytics'),
                'description' => get_string('panel:proctoring:description', 'block_dashboardanalytics'),
                'panels' => [
                    $this->panel('trustdistribution', get_string('panel:trustdistribution:title', 'block_dashboardanalytics'), 'donut', get_string('panel:trustdistribution:description', 'block_dashboardanalytics'), $proctoring->trust_distribution_items($filters)),
                    $this->panel('companytrust', get_string('panel:companytrust:title', 'block_dashboardanalytics'), 'bar', get_string('panel:companytrust:description', 'block_dashboardanalytics'), $proctoring->company_average_items($filters)),
                ],
            ];
        }

        if ($proctoring->has_reports($filters)) {
            return [
                'title' => get_string('panel:proctoring:title', 'block_dashboardanalytics'),
                'description' => get_string('panel:proctoring:partialdata', 'block_dashboardanalytics'),
                'panels' => [
                    $this->panel('proctoringcoverage', get_string('panel:proctoringcoverage:title', 'block_dashboardanalytics'), 'donut', get_string('panel:proctoringcoverage:description', 'block_dashboardanalytics'), $proctoring->coverage_items($filters)),
                    $this->panel('proctoringfeatures', get_string('panel:proctoringfeatures:title', 'block_dashboardanalytics'), 'cards', get_string('panel:proctoringfeatures:description', 'block_dashboardanalytics'), $proctoring->feature_items($filters)),
                ],
            ];
        }

        return [
            'title' => get_string('panel:proctoring:title', 'block_dashboardanalytics'),
            'description' => get_string('panel:proctoring:nodata', 'block_dashboardanalytics'),
            'panels' => [
                $this->panel('trustdistribution', get_string('panel:trustdistribution:title', 'block_dashboardanalytics'), 'donut', get_string('panel:trustdistribution:description', 'block_dashboardanalytics'), [
                    ['label' => get_string('panel:trusttrusted', 'block_dashboardanalytics'), 'value' => '0', 'percent' => 0.0, 'status' => 'ok', 'meta' => '90-100'],
                    ['label' => get_string('panel:trustreview', 'block_dashboardanalytics'), 'value' => '0', 'percent' => 0.0, 'status' => 'warning', 'meta' => '70-89'],
                    ['label' => get_string('panel:trustsuspicious', 'block_dashboardanalytics'), 'value' => '0', 'percent' => 0.0, 'status' => 'warning', 'meta' => '50-69'],
                    ['label' => get_string('panel:trustflagged', 'block_dashboardanalytics'), 'value' => '0', 'percent' => 0.0, 'status' => 'danger', 'meta' => '0-49'],
                ]),
            ],
        ];
    }

    private function forecast(array $filters): array {
        $documents = new document_repository();

        return [
            'title' => get_string('panel:forecast:title', 'block_dashboardanalytics'),
            'description' => get_string('panel:forecast:description', 'block_dashboardanalytics'),
            'panels' => [
                $this->panel('expirywindows', get_string('panel:expirywindows:title', 'block_dashboardanalytics'), 'cards', get_string('panel:expirywindows:description', 'block_dashboardanalytics'), $documents->forecast_window_items($filters)),
                $this->panel('forecastcompany', get_string('panel:forecastcompany:title', 'block_dashboardanalytics'), 'bar', get_string('panel:forecastcompany:description', 'block_dashboardanalytics'), $documents->risk_by_company_items($filters)),
            ],
        ];
    }

    private function server(array $filters): array {
        $server = new server_repository();

        return [
            'title' => get_string('panel:server:title', 'block_dashboardanalytics'),
            'description' => get_string('panel:server:description', 'block_dashboardanalytics'),
            'panels' => [
                $this->panel('servergauges', get_string('panel:servergauges:title', 'block_dashboardanalytics'), 'servergauges', get_string('panel:servergauges:description', 'block_dashboardanalytics'), $server->capacity_gauge_items()),
                $this->panel('serverforecast', get_string('panel:serverforecast:title', 'block_dashboardanalytics'), 'serverforecast', get_string('panel:serverforecast:description', 'block_dashboardanalytics'), $server->disk_forecast_items()),
                $this->panel('servererrors', get_string('panel:servererrors:title', 'block_dashboardanalytics'), 'servererrors', get_string('panel:servererrors:description', 'block_dashboardanalytics'), $server->error_summary_items()),
                $this->panel('serversettings', get_string('panel:serversettings:title', 'block_dashboardanalytics'), 'serversettings', get_string('panel:serversettings:description', 'block_dashboardanalytics'), $server->system_settings_items()),
            ],
        ];
    }

    private function client_manager_panels(string $tabkey, array $filters): array {
        if ($tabkey === 'turnover') {
            return $this->company_pending(
                get_string('panel:turnover:title', 'block_dashboardanalytics'),
                get_string('panel:turnover:pending', 'block_dashboardanalytics')
            );
        }

        if ($tabkey === 'compliance') {
            return $this->client_compliance($filters);
        }

        if ($tabkey === 'forecast' || $tabkey === 'expiry') {
            return $this->client_forecast($filters);
        }

        if ($tabkey === 'newstaff') {
            return $this->client_new_staff($filters);
        }

        return $this->client_overview($filters);
    }

    private function client_overview(array $filters): array {
        $documents = new document_repository();
        $employees = new employee_repository();

        return [
            'title' => get_string('panel:clientoverview:title', 'block_dashboardanalytics'),
            'description' => get_string('panel:clientoverview:description', 'block_dashboardanalytics'),
            'panels' => [
                $this->panel('clientdocumentstatus', get_string('panel:clientdocumentstatus:title', 'block_dashboardanalytics'), 'donut', get_string('panel:clientdocumentstatus:description', 'block_dashboardanalytics'), $documents->status_items($filters)),
                $this->panel('staffdistribution', get_string('panel:staffdistribution:title', 'block_dashboardanalytics'), 'grouped', get_string('panel:staffdistribution:description', 'block_dashboardanalytics'), $employees->staff_distribution_by_location_items($filters)),
                $this->panel('certstatusdepartment', get_string('panel:certstatusdepartment:title', 'block_dashboardanalytics'), 'stacked', get_string('panel:certstatusdepartment:description', 'block_dashboardanalytics'), $documents->certification_status_stacked_items($filters, 'department')),
            ],
        ];
    }

    private function client_compliance(array $filters): array {
        $documents = new document_repository();

        return [
            'title' => get_string('panel:clientcompliance:title', 'block_dashboardanalytics'),
            'description' => get_string('panel:clientcompliance:description', 'block_dashboardanalytics'),
            'panels' => [
                $this->panel('expiredexpiringdepartment', get_string('panel:expiredexpiringdepartment:title', 'block_dashboardanalytics'), 'grouped', get_string('panel:expiredexpiringdepartment:description', 'block_dashboardanalytics'), $documents->expired_expiring_grouped_items($filters, 'department')),
                $this->panel('expiredexpiringlocation', get_string('panel:expiredexpiringlocation:title', 'block_dashboardanalytics'), 'grouped', get_string('panel:expiredexpiringlocation:description', 'block_dashboardanalytics'), $documents->expired_expiring_grouped_items($filters, 'location')),
                $this->panel('coursecompliance', get_string('panel:coursecompliance:title', 'block_dashboardanalytics'), 'bar', get_string('panel:coursecompliance:description', 'block_dashboardanalytics'), $documents->noncompliance_by_course_items($filters)),
            ],
        ];
    }

    private function client_forecast(array $filters): array {
        $documents = new document_repository();

        return [
            'title' => get_string('panel:clientforecast:title', 'block_dashboardanalytics'),
            'description' => get_string('panel:clientforecast:description', 'block_dashboardanalytics'),
            'panels' => [
                $this->panel('clientexpirywindows', get_string('panel:clientexpirywindows:title', 'block_dashboardanalytics'), 'cards', get_string('panel:clientexpirywindows:description', 'block_dashboardanalytics'), $documents->forecast_window_items($filters)),
                $this->panel('weeklyforecast', get_string('panel:weeklyforecast:title', 'block_dashboardanalytics'), 'histogram', get_string('panel:weeklyforecast:description', 'block_dashboardanalytics'), $documents->weekly_expiry_histogram_items($filters)),
                $this->panel('clientforecastcourse', get_string('panel:clientforecastcourse:title', 'block_dashboardanalytics'), 'bar', get_string('panel:clientforecastcourse:description', 'block_dashboardanalytics'), $documents->noncompliance_by_course_items($filters)),
            ],
        ];
    }

    private function client_new_staff(array $filters): array {
        $employees = new employee_repository();
        $documents = new document_repository();

        return [
            'title' => get_string('panel:newstaff:title', 'block_dashboardanalytics'),
            'description' => get_string('panel:newstaff:description', 'block_dashboardanalytics'),
            'panels' => [
                $this->panel('newstaffrisk', get_string('panel:newstaffrisk:title', 'block_dashboardanalytics'), 'bar', get_string('panel:newstaffrisk:description', 'block_dashboardanalytics'), $employees->new_staff_risk_items($filters)),
                $this->panel('newstaffcoverage', get_string('panel:newstaffcoverage:title', 'block_dashboardanalytics'), 'donut', get_string('panel:newstaffcoverage:description', 'block_dashboardanalytics'), $documents->status_items($filters)),
            ],
        ];
    }

    private function employee_panels(string $tabkey, array $filters): array {
        $documents = new document_repository();

        if ($tabkey === 'certificates') {
            return [
                'title' => get_string('panel:certificates:title', 'block_dashboardanalytics'),
                'description' => get_string('panel:certificates:description', 'block_dashboardanalytics'),
                'panels' => [
                    $this->panel('employeedocumentstatus', get_string('panel:employeedocumentstatus:title', 'block_dashboardanalytics'), 'donut', get_string('panel:employeedocumentstatus:description', 'block_dashboardanalytics'), $documents->status_items($filters)),
                ],
            ];
        }

        if ($tabkey === 'courses') {
            return $this->company_pending(
                get_string('panel:courses:title', 'block_dashboardanalytics'),
                get_string('panel:courses:description', 'block_dashboardanalytics')
            );
        }

        return [
            'title' => get_string('panel:overview:title', 'block_dashboardanalytics'),
            'description' => get_string('panel:certificates:description', 'block_dashboardanalytics'),
            'panels' => [
                $this->panel('employeedocumentstatus', get_string('panel:employeedocumentstatus:title', 'block_dashboardanalytics'), 'donut', get_string('panel:certificates:description', 'block_dashboardanalytics'), $documents->status_items($filters)),
            ],
        ];
    }

    private function company_pending(string $title, string $description): array {
        return [
            'title' => $title,
            'description' => $description,
            'panels' => [
                $this->panel('pending', get_string('kpi:help:datapending', 'block_dashboardanalytics'), 'cards', $description, [[
                    'label' => get_string('kpi:help:datapending', 'block_dashboardanalytics'),
                    'value' => get_string('kpi:value:pending', 'block_dashboardanalytics'),
                    'percent' => 0.0,
                    'status' => 'muted',
                    'meta' => get_string('kpi:help:lmsintegration', 'block_dashboardanalytics'),
                ]]),
            ],
        ];
    }

    private function panel(string $key, string $title, string $type, string $description, array $items): array {
        foreach ($items as $index => $item) {
            if (!isset($item['segments'])) {
                $items[$index]['segments'] = [];
            }
        }

        return [
            'key' => $key,
            'title' => $title,
            'type' => $type,
            'description' => $description,
            'items' => array_values($items),
        ];
    }
}
