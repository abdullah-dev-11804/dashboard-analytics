<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\service;

use block_dashboardanalytics\permissions;
use block_dashboardanalytics\repository\company_repository;
use block_dashboardanalytics\repository\document_repository;
use block_dashboardanalytics\repository\eds_repository;
use block_dashboardanalytics\repository\employee_repository;
use block_dashboardanalytics\repository\proctoring_repository;

defined('MOODLE_INTERNAL') || die();

class visual_service {

    public function panels(string $dashboardkey, string $tabkey, array $filters): array {
        if ($dashboardkey === permissions::DASHBOARD_CLIENT_MANAGER) {
            return $this->client_manager_panels($tabkey, $filters);
        }

        if ($dashboardkey !== permissions::DASHBOARD_COORDINATOR) {
            return [
                'title' => 'Dashboard visuals',
                'description' => '',
                'panels' => [],
            ];
        }

        if ($tabkey === 'overview') {
            return $this->overview($filters);
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

        return $this->overview($filters);
    }

    private function overview(array $filters): array {
        $documents = new document_repository();
        $companies = new company_repository();

        return [
            'title' => 'Overview',
            'description' => 'Coordinator snapshot of compliance status and company-level performance.',
            'panels' => [
                $this->panel('documentstatus', 'Document status distribution', 'donut', 'Active, expiring, expired and missing document coverage.', $documents->status_items($filters)),
                $this->panel('companycompliance', 'Compliance by company', 'bar', 'Worst companies are shown first so coordinators can act quickly.', $companies->compliance_items($filters)),
            ],
        ];
    }

    private function compliance(array $filters): array {
        $documents = new document_repository();
        $eds = new eds_repository();
        $edsrows = $eds->pending_manual_rows($filters, 0, 1);

        return [
            'title' => 'Compliance',
            'description' => 'Operational compliance visuals for expired, expiring and pending signature work.',
            'panels' => [
                $this->panel('riskcompany', 'Expired and expiring by company', 'bar', 'Companies ordered by total at-risk document count.', $documents->risk_by_company_items($filters)),
                $this->panel('riskcourse', 'Non-compliance by course', 'bar', 'Courses with documents already expired or expiring within 30 days.', $documents->noncompliance_by_course_items($filters)),
                $this->panel('edsqueue', 'EDS queue', 'cards', 'Pending manual signatures waiting for the current expected signer.', [[
                    'label' => 'Pending manual',
                    'value' => (string)$edsrows['totalcount'],
                    'percent' => min(100, (float)$edsrows['totalcount']),
                    'status' => $edsrows['totalcount'] > 0 ? 'warning' : 'ok',
                    'meta' => 'NCASign signer queue',
                ]]),
            ],
        ];
    }

    private function proctoring(array $filters): array {
        $proctoring = new proctoring_repository();

        if ($proctoring->has_data($filters)) {
            return [
                'title' => 'Proctoring',
                'description' => 'Quilgo trust score distribution and company-level proctoring risk.',
                'panels' => [
                    $this->panel('trustdistribution', 'Trust score distribution', 'donut', 'Attempts grouped into Trusted, Review, Suspicious and Flagged bands.', $proctoring->trust_distribution_items($filters)),
                    $this->panel('companytrust', 'Average trust score by company', 'bar', 'Companies with lower average trust scores appear first.', $proctoring->company_average_items($filters)),
                ],
            ];
        }

        if ($proctoring->has_reports($filters)) {
            return [
                'title' => 'Proctoring',
                'description' => 'Quilgo report rows exist, but stat is empty so trust scores are not available yet. Showing proctoring coverage instead.',
                'panels' => [
                    $this->panel('proctoringcoverage', 'Proctoring coverage', 'donut', 'Attempts with Quilgo proctoring enabled versus reports without proctoring enabled.', $proctoring->coverage_items($filters)),
                    $this->panel('proctoringfeatures', 'Capture features', 'cards', 'Camera, screen, force mode and captured errors from Quilgo report rows.', $proctoring->feature_items($filters)),
                ],
            ];
        }

        return [
            'title' => 'Proctoring',
            'description' => 'No Quilgo report rows were found for the current filters.',
            'panels' => [
                $this->panel('trustdistribution', 'Trust score distribution', 'donut', 'No parsable Quilgo trust scores yet.', [
                    ['label' => 'Trusted', 'value' => '0', 'percent' => 0.0, 'status' => 'ok', 'meta' => '90-100'],
                    ['label' => 'Review', 'value' => '0', 'percent' => 0.0, 'status' => 'warning', 'meta' => '70-89'],
                    ['label' => 'Suspicious', 'value' => '0', 'percent' => 0.0, 'status' => 'warning', 'meta' => '50-69'],
                    ['label' => 'Flagged', 'value' => '0', 'percent' => 0.0, 'status' => 'danger', 'meta' => '0-49'],
                ]),
            ],
        ];
    }

    private function forecast(array $filters): array {
        $documents = new document_repository();

        return [
            'title' => 'Forecast',
            'description' => 'Scheduling pressure based on upcoming NCASign document expiry dates.',
            'panels' => [
                $this->panel('expirywindows', '30/60/90 day workload', 'cards', 'At-a-glance retraining load for the next three windows.', $documents->forecast_window_items($filters)),
                $this->panel('forecastcompany', 'Upcoming risk by company', 'bar', 'Companies with the most expired or soon-expiring documents.', $documents->risk_by_company_items($filters)),
            ],
        ];
    }

    private function client_manager_panels(string $tabkey, array $filters): array {
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
            'title' => 'Overview',
            'description' => 'Client manager view focused on department, location and site-level compliance.',
            'panels' => [
                $this->panel('clientdocumentstatus', 'Document status distribution', 'donut', 'Active, expiring, expired and missing document coverage for the current scope.', $documents->status_items($filters)),
                $this->panel('staffdistribution', 'Staff distribution - department x location', 'grouped', 'Headcount per location, split by department.', $employees->staff_distribution_by_location_items($filters)),
                $this->panel('certstatusdepartment', 'Certification status by department', 'stacked', 'Active, expiring and expired document counts by department.', $documents->certification_status_stacked_items($filters, 'department')),
            ],
        ];
    }

    private function client_compliance(array $filters): array {
        $documents = new document_repository();

        return [
            'title' => 'Compliance',
            'description' => 'Compliance breakdowns for the client manager scope.',
            'panels' => [
                $this->panel('expiredexpiringdepartment', 'Expired & expiring - by department', 'grouped', 'Expired now versus expiring within 30 days.', $documents->expired_expiring_grouped_items($filters, 'department')),
                $this->panel('expiredexpiringlocation', 'Expired & expiring - by location', 'grouped', 'Expired now versus expiring within 30 days.', $documents->expired_expiring_grouped_items($filters, 'location')),
                $this->panel('coursecompliance', 'Non-compliance by course', 'bar', 'Courses with documents expired or expiring soon.', $documents->noncompliance_by_course_items($filters)),
            ],
        ];
    }

    private function client_forecast(array $filters): array {
        $documents = new document_repository();

        return [
            'title' => '30/60/90 days',
            'description' => 'Upcoming expiry workload for the current client manager scope.',
            'panels' => [
                $this->panel('clientexpirywindows', '30/60/90 day expiry workload', 'cards', 'Retraining load by upcoming expiry window.', $documents->forecast_window_items($filters)),
                $this->panel('weeklyforecast', '13-week expiry forecast histogram', 'histogram', 'Week-by-week expiry pressure across the next quarter.', $documents->weekly_expiry_histogram_items($filters)),
                $this->panel('clientforecastcourse', 'Upcoming expiry by course', 'bar', 'Courses contributing most to near-term retraining pressure.', $documents->noncompliance_by_course_items($filters)),
            ],
        ];
    }

    private function client_new_staff(array $filters): array {
        $employees = new employee_repository();
        $documents = new document_repository();

        return [
            'title' => 'New staff',
            'description' => 'New-staff onboarding risk and certification coverage.',
            'panels' => [
                $this->panel('newstaffrisk', 'New staff by department', 'bar', 'New users created in the last 90 days, grouped by department.', $employees->new_staff_risk_items($filters)),
                $this->panel('newstaffcoverage', 'Current document coverage', 'donut', 'Document coverage for the currently filtered user scope.', $documents->status_items($filters)),
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
