<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');

use block_dashboardanalytics\context_resolver;
use block_dashboardanalytics\filters;
use block_dashboardanalytics\permissions;
use block_dashboardanalytics\repository\document_repository;

$contextid = required_param('contextid', PARAM_INT);
$dashboardkey = required_param('dashboardkey', PARAM_ALPHANUMEXT);
$drilldownkey = required_param('drilldownkey', PARAM_ALPHANUMEXT);
$filtersjson = optional_param('filters', '{}', PARAM_RAW);
$scope = optional_param('scope', 'visible', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 20, PARAM_INT);
require_login();
require_sesskey();

$context = context_resolver::require_context($contextid);
$resolveddashboard = permissions::require_dashboard_key($context, $dashboardkey, (int)$USER->id);
$showidentity = permissions::can_view_employee_identity($context)
    || $resolveddashboard === permissions::DASHBOARD_EMPLOYEE;
$scopedfilters = filters::apply_dashboard_scope(filters::from_json($filtersjson), $resolveddashboard, (int)$USER->id);

$status = '';
if ($drilldownkey === 'company_expired_documents' || $drilldownkey === 'client_expired_documents') {
    $status = 'expired';
} else if ($drilldownkey === 'company_expiring_documents' || $drilldownkey === 'client_expiring_documents') {
    $status = 'expiring';
} else if ($drilldownkey === 'company_course_noncompliance') {
    $status = 'noncompliant';
} else if (!empty($scopedfilters['status'])) {
    $status = (string)$scopedfilters['status'];
}

$allowed = [
    permissions::DASHBOARD_COMPANY => [
        'company_compliance',
        'company_expiring_documents',
        'company_expired_documents',
        'company_course_noncompliance',
        'company_forecast_documents',
    ],
    permissions::DASHBOARD_CLIENT => [
        'client_compliance',
        'client_expiring_documents',
        'client_expired_documents',
        'client_forecast_documents',
    ],
    permissions::DASHBOARD_EMPLOYEE => [
        'employee_documents',
    ],
];

if (!in_array($drilldownkey, $allowed[$resolveddashboard] ?? [], true)) {
    throw new moodle_exception('error:noaccess', 'block_dashboardanalytics');
}

$documents = new document_repository();
$scope = $scope === 'all' ? 'all' : 'visible';
$page = max(0, $page);
$perpage = min(100, max(10, $perpage));

$export = $scope === 'all'
    ? $documents->document_table_export_rows($scopedfilters, $status, $showidentity)
    : $documents->document_table_export_rows($scopedfilters, $status, $showidentity, $page, $perpage);
$columns = $export['columns'] ?? [];
$rows = $export['rows'] ?? [];

$filenamesuffix = $scope === 'all' ? 'all' : 'page-' . ($page + 1);
$filename = clean_filename('learning-matrix-' . $filenamesuffix . '-' . userdate(time(), '%Y%m%d-%H%M') . '.csv');
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
fputcsv($output, array_values($columns));
foreach ($rows as $row) {
    $line = [];
    foreach (array_keys($columns) as $key) {
        $line[] = (string)($row[$key] ?? '');
    }
    fputcsv($output, $line);
}
fclose($output);
exit;
