<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');

use block_dashboardanalytics\context_resolver;
use block_dashboardanalytics\filters;
use block_dashboardanalytics\permissions;
use block_dashboardanalytics\repository\document_repository;
use block_dashboardanalytics\repository\report_repository;
use block_dashboardanalytics\service\report_service;

function block_dashboardanalytics_export_xml(string $value): string {
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function block_dashboardanalytics_export_column_letter(int $index): string {
    $index++;
    $letters = '';
    while ($index > 0) {
        $index--;
        $letters = chr(65 + ($index % 26)) . $letters;
        $index = intdiv($index, 26);
    }
    return $letters;
}

function block_dashboardanalytics_export_xlsx_cell(int $row, int $column, string $value): string {
    if ($value === '') {
        return '';
    }

    $ref = block_dashboardanalytics_export_column_letter($column) . $row;
    return '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">'
        . block_dashboardanalytics_export_xml($value)
        . '</t></is></c>';
}

function block_dashboardanalytics_export_xlsx_sheet(array $rows): string {
    $maxcolumns = 1;
    foreach ($rows as $row) {
        $maxcolumns = max($maxcolumns, count($row));
    }

    $lastrow = max(1, count($rows));
    $lastcell = block_dashboardanalytics_export_column_letter($maxcolumns - 1) . $lastrow;
    $xmlrows = '';
    foreach ($rows as $rowindex => $row) {
        $rownumber = $rowindex + 1;
        $cells = '';
        foreach (array_values($row) as $columnindex => $value) {
            $cells .= block_dashboardanalytics_export_xlsx_cell($rownumber, $columnindex, (string)$value);
        }
        $xmlrows .= '<row r="' . $rownumber . '">' . $cells . '</row>';
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<dimension ref="A1:' . $lastcell . '"/>'
        . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="15"/>'
        . '<sheetData>' . $xmlrows . '</sheetData>'
        . '</worksheet>';
}

function block_dashboardanalytics_export_xlsx(string $filepath, array $rows, string $title): void {
    if (!class_exists('ZipArchive')) {
        throw new moodle_exception('error:exportfailed', 'block_dashboardanalytics', '', 'ZipArchive is required for XLSX export.');
    }

    $zip = new ZipArchive();
    if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new moodle_exception('error:exportfailed', 'block_dashboardanalytics', '', 'Unable to create XLSX export.');
    }

    $created = gmdate('Y-m-d\TH:i:s\Z');
    $safebooktitle = block_dashboardanalytics_export_xml($title);
    $sheetxml = block_dashboardanalytics_export_xlsx_sheet($rows);

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
        . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
        . '</Relationships>');
    $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
        . 'xmlns:dc="http://purl.org/dc/elements/1.1/" '
        . 'xmlns:dcterms="http://purl.org/dc/terms/" '
        . 'xmlns:dcmitype="http://purl.org/dc/dcmitype/" '
        . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
        . '<dc:title>' . $safebooktitle . '</dc:title>'
        . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:created>'
        . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:modified>'
        . '</cp:coreProperties>');
    $zip->addFromString('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
        . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
        . '<Application>Moodle</Application><DocSecurity>0</DocSecurity><ScaleCrop>false</ScaleCrop>'
        . '<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant>'
        . '<vt:variant><vt:i4>1</vt:i4></vt:variant></vt:vector></HeadingPairs>'
        . '<TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>Report</vt:lpstr></vt:vector></TitlesOfParts>'
        . '</Properties>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>');
    $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>');
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetxml);
    $zip->close();
}

function block_dashboardanalytics_export_company_label(array $filters, report_repository $repository): string {
    $ids = array_values(array_filter(array_map('intval', $filters['companyids'] ?? [])));
    $labels = [];
    foreach ($ids as $id) {
        $label = trim((string)$repository->company_name($id));
        if ($label !== '') {
            $labels[] = format_string($label);
        }
    }

    if (!$labels && !empty($filters['companies']) && is_array($filters['companies'])) {
        $labels = array_values(array_filter(array_map('strval', $filters['companies'])));
    }

    return implode(', ', $labels);
}

function block_dashboardanalytics_export_period_label(array $options): string {
    if (($options['periodmode'] ?? 'month') === 'custom') {
        $start = trim((string)($options['customstart'] ?? ''));
        $end = trim((string)($options['customend'] ?? ''));
        if ($start !== '' || $end !== '') {
            return trim($start . ' - ' . $end, ' -');
        }
        return 'Custom range';
    }

    $months = array_values(array_filter(array_map('intval', $options['months'] ?? []), static function(int $month): bool {
        return $month >= 1 && $month <= 12;
    }));
    $years = array_values(array_filter(array_map('intval', $options['years'] ?? []), static function(int $year): bool {
        return $year >= 2000 && $year <= 2100;
    }));

    if (!$months && !empty($options['month'])) {
        $months = [(int)$options['month']];
    }
    if (!$years && !empty($options['year'])) {
        $years = [(int)$options['year']];
    }

    $monthlabels = [];
    foreach ($months as $month) {
        $monthlabels[] = userdate(make_timestamp(2024, $month, 1), '%B');
    }

    return trim(implode(', ', $monthlabels) . ' ' . implode(', ', $years));
}

function block_dashboardanalytics_export_filter_label(array $options, string $companylabel): string {
    $parts = [];
    if ($companylabel !== '') {
        $parts[] = get_string('label:company', 'block_dashboardanalytics') . ': ' . $companylabel;
    }
    $search = trim((string)($options['search'] ?? ''));
    if ($search !== '') {
        $parts[] = 'Search: ' . $search;
    }

    return $parts ? implode('; ', $parts) : 'None';
}

function block_dashboardanalytics_export_filename_base(string $title): string {
    $title = trim($title);
    if ($title === '') {
        $title = 'report-builder';
    }

    $filename = clean_filename($title);
    return $filename !== '' ? $filename : 'report-builder';
}

function block_dashboardanalytics_export_xlsx_rows(array $export, string $title, string $companylabel, string $periodlabel, string $filterlabel): array {
    $summary = $export['summary'] ?? [];
    $columns = $export['columns'] ?? [];
    $rows = $export['rows'] ?? [];

    $sheetrows = [
        ['Title', $title],
        ['Company', $companylabel],
        ['Period', $periodlabel],
        ['Filters', $filterlabel],
        ['Generated', userdate(time(), '%Y-%m-%d %H:%M')],
        ['Trained, total', (string)($summary['total'] ?? count($rows))],
        ['Online', (string)($summary['online'] ?? 0)],
        ['Offline', (string)($summary['offline'] ?? 0)],
        [],
        array_map(static function(array $column): string {
            return (string)($column['label'] ?? $column['key'] ?? '');
        }, $columns),
    ];

    foreach ($rows as $row) {
        $sheetrows[] = array_map(static function(array $column) use ($row): string {
            $key = (string)($column['key'] ?? '');
            return (string)($row[$key] ?? '');
        }, $columns);
    }

    return $sheetrows;
}

function block_dashboardanalytics_export_report_file(array $row): ?stored_file {
    $sourcekind = (string)($row['sourcekind'] ?? '');
    $itemid = (int)($row['sourceid'] ?? $row['documentid'] ?? 0);
    if ($itemid <= 0) {
        return null;
    }

    if ($sourcekind === 'ncasign') {
        $component = 'local_ncasign';
        $filearea = 'signedpdf';
    } else if ($sourcekind === 'legacy_type1') {
        $component = 'local_sentaldocupload';
        $filearea = 'document';
    } else {
        return null;
    }

    $fs = get_file_storage();
    $files = $fs->get_area_files(\context_system::instance()->id, $component, $filearea, $itemid, 'id DESC', false);
    foreach ($files as $file) {
        return $file;
    }

    return null;
}

function block_dashboardanalytics_export_archive_part(string $value, string $fallback): string {
    $value = clean_filename(trim($value));
    $value = rtrim($value, " .");
    if ($value === '') {
        return $fallback;
    }

    $reserved = [
        'CON', 'PRN', 'AUX', 'NUL',
        'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9',
        'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9',
    ];
    if (in_array(strtoupper($value), $reserved, true)) {
        $value = '_' . $value;
    }

    return $value;
}

function block_dashboardanalytics_export_archive_path(array &$used, string $course, string $fullname, string $shortname, string $originalfilename): string {
    $folder = block_dashboardanalytics_export_archive_part($course, 'Course');
    $extension = pathinfo($originalfilename, PATHINFO_EXTENSION);
    $extension = $extension !== '' ? '.' . $extension : '.pdf';
    $basename = block_dashboardanalytics_export_archive_part(trim($fullname . ' ' . $shortname), 'document');
    $path = $folder . '/' . $basename . $extension;
    $candidate = $path;
    $counter = 2;

    while (isset($used[$candidate])) {
        $candidate = $folder . '/' . $basename . ' ' . $counter . $extension;
        $counter++;
    }

    $used[$candidate] = true;
    return $candidate;
}

function block_dashboardanalytics_export_zip(string $filepath, array $rawrows): void {
    if (!class_exists('ZipArchive')) {
        throw new moodle_exception('error:exportfailed', 'block_dashboardanalytics', '', 'ZipArchive is required for ZIP export.');
    }

    $zip = new ZipArchive();
    if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new moodle_exception('error:exportfailed', 'block_dashboardanalytics', '', 'Unable to create ZIP export.');
    }

    $used = [];
    $added = 0;
    foreach ($rawrows as $row) {
        $file = block_dashboardanalytics_export_report_file($row);
        if (!$file) {
            continue;
        }

        $entry = block_dashboardanalytics_export_archive_path(
            $used,
            (string)($row['course'] ?? ''),
            (string)($row['fullname'] ?? ''),
            (string)($row['courseshortname'] ?? ''),
            $file->get_filename()
        );
        if ($zip->addFromString($entry, $file->get_content()) === false) {
            $zip->close();
            throw new moodle_exception('error:exportfailed', 'block_dashboardanalytics', '', 'Unable to add ZIP entry ' . $entry . '.');
        }
        $added++;
    }

    if ($added === 0) {
        if ($zip->addFromString('no-documents.txt', 'No signed documents were found for the current report.') === false) {
            $zip->close();
            throw new moodle_exception('error:exportfailed', 'block_dashboardanalytics', '', 'Unable to add fallback ZIP entry.');
        }
    }

    if ($zip->close() !== true) {
        throw new moodle_exception('error:exportfailed', 'block_dashboardanalytics', '', 'Unable to finalize ZIP export.');
    }
}

function block_dashboardanalytics_export_send_file(string $filepath, string $filename, string $mimetype): void {
    if (class_exists('\core\session\manager')) {
        \core\session\manager::write_close();
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: ' . $mimetype);
    header('X-Content-Type-Options: nosniff');
    header('Content-Transfer-Encoding: binary');
    header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    @unlink($filepath);
    exit;
}

$contextid = required_param('contextid', PARAM_INT);
$dashboardkey = required_param('dashboardkey', PARAM_ALPHANUMEXT);
$reportbuilder = optional_param('reportbuilder', 0, PARAM_BOOL);
$format = optional_param('format', 'csv', PARAM_ALPHA);
$drilldownkey = $reportbuilder ? '' : required_param('drilldownkey', PARAM_ALPHANUMEXT);
$filtersjson = optional_param('filters', '{}', PARAM_RAW);
$optionsjson = optional_param('options', '{}', PARAM_RAW);
$scope = optional_param('scope', 'visible', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 20, PARAM_INT);
$sortkey = optional_param('sortkey', 'completiondate', PARAM_TEXT);
$sortdir = optional_param('sortdir', 'asc', PARAM_ALPHA);
require_login();
require_sesskey();

$context = context_resolver::require_context($contextid);
$resolveddashboard = permissions::require_dashboard_key($context, $dashboardkey, (int)$USER->id);
$scope = $scope === 'all' ? 'all' : 'visible';
$page = max(0, $page);
$perpage = min(100, max(10, $perpage));

if ($reportbuilder) {
    if (!is_siteadmin((int)$USER->id)) {
        throw new moodle_exception('error:noaccess', 'block_dashboardanalytics');
    }

    $service = new report_service();
    $repository = new report_repository();
    $filters = filters::from_json($filtersjson);
    $options = json_decode($optionsjson, true);
    $options = is_array($options) ? $options : [];
    $export = $service->builder_export($filters, $options, $sortkey, $sortdir);
    $title = trim((string)($options['templatename'] ?? ''));
    if ($title === '') {
        $title = get_string('reportsbuilder:untitledtemplate', 'block_dashboardanalytics');
    }

    $companylabel = block_dashboardanalytics_export_company_label($filters, $repository);
    $periodlabel = block_dashboardanalytics_export_period_label($options);
    $filterlabel = block_dashboardanalytics_export_filter_label($options, $companylabel);
    $basename = block_dashboardanalytics_export_filename_base($title);
    $filepath = tempnam(sys_get_temp_dir(), 'da_export_');

    if ($format === 'zip') {
        block_dashboardanalytics_export_zip($filepath, $export['rawrows'] ?? []);
        block_dashboardanalytics_export_send_file(
            $filepath,
            clean_filename($basename . '.zip'),
            'application/zip'
        );
    }

    block_dashboardanalytics_export_xlsx(
        $filepath,
        block_dashboardanalytics_export_xlsx_rows($export, $title, $companylabel, $periodlabel, $filterlabel),
        $title
    );
    block_dashboardanalytics_export_send_file(
        $filepath,
        clean_filename($basename . '.xlsx'),
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
}

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
$export = $scope === 'all'
    ? $documents->document_table_export_rows($scopedfilters, $status, $showidentity)
    : $documents->document_table_export_rows($scopedfilters, $status, $showidentity, $page, $perpage);
$columns = $export['columns'] ?? [];
$rows = $export['rows'] ?? [];
$filename = clean_filename('learning-matrix-' . ($scope === 'all' ? 'all' : 'page-' . ($page + 1)) . '-' . userdate(time(), '%Y%m%d-%H%M') . '.csv');

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
