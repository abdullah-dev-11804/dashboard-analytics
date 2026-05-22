<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\service;

use block_dashboardanalytics\permissions;
use block_dashboardanalytics\repository\company_repository;
use block_dashboardanalytics\repository\document_repository;
use block_dashboardanalytics\repository\eds_repository;
use block_dashboardanalytics\repository\proctoring_repository;

defined('MOODLE_INTERNAL') || die();

class visual_service {

    public function panels(string $dashboardkey, string $tabkey, array $filters): array {
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

        return [
            'title' => 'Proctoring',
            'description' => 'Quilgo tables were found but no parsable trust score values were available in quizaccess_quilgo_reports.stat for the current filters.',
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

    private function panel(string $key, string $title, string $type, string $description, array $items): array {
        return [
            'key' => $key,
            'title' => $title,
            'type' => $type,
            'description' => $description,
            'items' => array_values($items),
        ];
    }
}
