<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

use block_dashboardanalytics\permissions;
use block_dashboardanalytics\service\recompletion_bridge;

defined('MOODLE_INTERNAL') || die();

class expiry_workflow_repository {
    public const STATUS_AWAITING = 'awaiting';
    public const STATUS_REASSIGNED = 'reassigned';
    public const STATUS_DISMISSED = 'dismissed';

    public const CADENCE_DAILY = 'daily';
    public const CADENCE_EVERY3DAYS = 'every3days';
    public const CADENCE_WEEKLY = 'weekly';

    public function can_view_panel(int $userid): bool {
        if (is_siteadmin($userid) || permissions::is_company_owner(\context_system::instance(), $userid)) {
            return true;
        }

        return !empty($this->recipient_company_ids_for_user($userid));
    }

    public function can_manage_company(int $userid, int $companyid): bool {
        if ($companyid <= 0) {
            return false;
        }

        if (is_siteadmin($userid)) {
            return true;
        }

        $companies = new company_repository();
        $scope = $companies->scope_details_for_user($userid);
        if (!empty($scope['companyids']) && in_array($companyid, array_map('intval', $scope['companyids']), true)
                && permissions::is_company_owner(\context_system::instance(), $userid)) {
            return true;
        }

        return in_array($companyid, $this->recipient_company_ids_for_user($userid), true);
    }

    public function can_manage_settings(int $userid, int $companyid): bool {
        if ($companyid <= 0) {
            return is_siteadmin($userid);
        }

        if (is_siteadmin($userid)) {
            return true;
        }

        $companies = new company_repository();
        $scope = $companies->scope_details_for_user($userid);
        return !empty($scope['companyids'])
            && in_array($companyid, array_map('intval', $scope['companyids']), true)
            && permissions::is_company_owner(\context_system::instance(), $userid);
    }

    public function threshold_days(): int {
        $days = (int)get_config('block_dashboardanalytics', 'expiryworkflowthresholddays');
        return $days > 0 ? $days : 30;
    }

    public function master_enabled(): bool {
        return !empty(get_config('block_dashboardanalytics', 'expiryworkflowenabled'));
    }

    public function default_recipient_email(): string {
        $email = trim((string)get_config('block_dashboardanalytics', 'expiryworkflowdefaultrecipient'));
        return $email !== '' ? $email : 'trainingmng1@sental.kz';
    }

    public function manageable_company_options(int $userid): array {
        global $DB;

        $companies = [];
        if (is_siteadmin($userid)) {
            foreach ($DB->get_records('company', null, 'name ASC', 'id, name') as $record) {
                $companies[(int)$record->id] = format_string((string)$record->name);
            }
        } else {
            $companyrepo = new company_repository();
            $scope = $companyrepo->scope_details_for_user($userid);
            foreach (array_map('intval', $scope['companyids'] ?? []) as $companyid) {
                if ($companyid > 0) {
                    $companies[$companyid] = (string)$DB->get_field('company', 'name', ['id' => $companyid], IGNORE_MISSING);
                }
            }

            foreach ($this->recipient_company_ids_for_user($userid) as $companyid) {
                if (!isset($companies[$companyid])) {
                    $companies[$companyid] = (string)$DB->get_field('company', 'name', ['id' => $companyid], IGNORE_MISSING);
                }
            }
        }

        asort($companies);
        $options = [];
        foreach ($companies as $companyid => $name) {
            if ($companyid <= 0 || trim((string)$name) === '') {
                continue;
            }
            $options[] = [
                'value' => (string)$companyid,
                'label' => format_string((string)$name),
            ];
        }

        return $options;
    }

    public function resolve_companyid_for_user(int $userid, int $requestedcompanyid = 0): int {
        $options = $this->manageable_company_options($userid);
        if (!$options) {
            return 0;
        }

        $allowed = array_map(static fn(array $row): int => (int)$row['value'], $options);
        if ($requestedcompanyid > 0 && in_array($requestedcompanyid, $allowed, true)) {
            return $requestedcompanyid;
        }

        return (int)$allowed[0];
    }

    public function company_config(int $companyid): array {
        global $DB;

        $record = $DB->get_record('block_da_expcompany', ['companyid' => $companyid], '*', IGNORE_MISSING);
        return [
            'enabled' => $record ? !empty($record->enabled) : true,
            'recipientids' => $this->csv_to_ints($record->recipientids ?? ''),
        ];
    }

    public function panel_data(
        int $userid,
        int $selectedcompanyid = 0,
        string $coursesearch = '',
        int $coursepage = 0,
        int $courseperpage = 20,
        string $casesearch = '',
        string $casestatus = '',
        int $casepage = 0,
        int $caseperpage = 20
    ): array {
        $companyoptions = $this->manageable_company_options($userid);
        $companyid = $this->resolve_companyid_for_user($userid, $selectedcompanyid);
        $companyconfig = $companyid > 0 ? $this->company_config($companyid) : ['enabled' => true, 'recipientids' => []];
        $coursecontrols = $this->list_company_courses($companyid, $coursesearch, $coursepage, $courseperpage);
        $cases = $this->list_cases($companyid, $casesearch, $casestatus, $casepage, $caseperpage);
        $counts = $this->case_counts($companyid);

        return [
            'site' => [
                'enabled' => $this->master_enabled(),
                'thresholddays' => $this->threshold_days(),
                'defaultrecipient' => $this->default_recipient_email(),
                'cansavesite' => is_siteadmin($userid),
            ],
            'company' => [
                'companyid' => $companyid,
                'companyoptions' => $companyoptions,
                'selectorvisible' => count($companyoptions) > 1,
                'enabled' => $companyconfig['enabled'],
                'recipientids' => array_map('strval', $companyconfig['recipientids']),
                'recipientoptions' => $companyid > 0 ? $this->recipient_options($companyid) : [],
                'cansavecompany' => $this->can_manage_settings($userid, $companyid),
            ],
            'counters' => [
                ['key' => self::STATUS_AWAITING, 'label' => $this->workflow_status_label(self::STATUS_AWAITING), 'value' => (string)$counts[self::STATUS_AWAITING]],
                ['key' => self::STATUS_REASSIGNED, 'label' => $this->workflow_status_label(self::STATUS_REASSIGNED), 'value' => (string)$counts[self::STATUS_REASSIGNED]],
                ['key' => self::STATUS_DISMISSED, 'label' => $this->workflow_status_label(self::STATUS_DISMISSED), 'value' => (string)$counts[self::STATUS_DISMISSED]],
            ],
            'courses' => $coursecontrols,
            'cases' => $cases,
            'cadenceoptions' => $this->cadence_options(),
            'canmanagecases' => $this->can_manage_company($userid, $companyid),
        ];
    }

    public function save_site_settings(bool $enabled, int $thresholddays, string $defaultrecipient): void {
        set_config('expiryworkflowenabled', $enabled ? 1 : 0, 'block_dashboardanalytics');
        set_config('expiryworkflowthresholddays', max(1, $thresholddays), 'block_dashboardanalytics');
        set_config('expiryworkflowdefaultrecipient', trim($defaultrecipient), 'block_dashboardanalytics');
    }

    public function save_company_config(int $companyid, bool $enabled, array $recipientids, int $actorid): void {
        global $DB;

        $now = time();
        $record = $DB->get_record('block_da_expcompany', ['companyid' => $companyid], '*', IGNORE_MISSING);
        $payload = (object)[
            'companyid' => $companyid,
            'enabled' => $enabled ? 1 : 0,
            'recipientids' => $this->ints_to_csv($recipientids),
            'timemodified' => $now,
            'modifiedby' => $actorid,
        ];

        if ($record) {
            $payload->id = (int)$record->id;
            $DB->update_record('block_da_expcompany', $payload);
            return;
        }

        $payload->timecreated = $now;
        $DB->insert_record('block_da_expcompany', $payload);
    }

    public function course_enabled(int $courseid): bool {
        global $DB;

        $record = $DB->get_record('block_da_expcourse', ['courseid' => $courseid], 'enabled', IGNORE_MISSING);
        return $record ? !empty($record->enabled) : true;
    }

    public function set_course_enabled(int $courseid, bool $enabled, int $actorid): void {
        global $DB;

        $now = time();
        $record = $DB->get_record('block_da_expcourse', ['courseid' => $courseid], '*', IGNORE_MISSING);
        $payload = (object)[
            'courseid' => $courseid,
            'enabled' => $enabled ? 1 : 0,
            'timemodified' => $now,
            'modifiedby' => $actorid,
        ];

        if ($record) {
            $payload->id = (int)$record->id;
            $DB->update_record('block_da_expcourse', $payload);
            return;
        }

        $payload->timecreated = $now;
        $DB->insert_record('block_da_expcourse', $payload);
    }

    public function recipient_options(int $companyid): array {
        global $DB;

        if ($companyid <= 0) {
            return [];
        }

        $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email
                  FROM {company_users} cu
                  JOIN {user} u ON u.id = cu.userid
                 WHERE cu.companyid = :companyid
                   AND u.deleted = 0
                   AND u.suspended = 0
              ORDER BY u.lastname ASC, u.firstname ASC";

        $options = [];
        foreach ($DB->get_records_sql($sql, ['companyid' => $companyid]) as $record) {
            $options[] = [
                'value' => (string)$record->id,
                'label' => fullname($record) . ' · ' . $record->email,
            ];
        }

        return $options;
    }

    public function list_company_courses(int $companyid, string $search = '', int $page = 0, int $perpage = 20): array {
        global $DB;

        $page = max(0, $page);
        $perpage = max(10, min(100, $perpage));
        $params = ['siteid' => SITEID];
        $where = ['c.id <> :siteid', 'c.visible = 1'];

        if ($companyid > 0) {
            $where[] = 'cu.companyid = :companyid';
            $params['companyid'] = $companyid;
        }

        if ($search !== '') {
            $like = '%' . $DB->sql_like_escape($search) . '%';
            $where[] = '(' . $DB->sql_like('c.fullname', ':csearch1', false) . ' OR ' . $DB->sql_like('c.shortname', ':csearch2', false) . ')';
            $params['csearch1'] = $like;
            $params['csearch2'] = $like;
        }

        $sql = "SELECT c.id, c.fullname, c.shortname
                  FROM {course} c
             LEFT JOIN {enrol} e ON e.courseid = c.id AND e.status = 0
             LEFT JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.status = 0
             LEFT JOIN {company_users} cu ON cu.userid = ue.userid
                 WHERE " . implode(' AND ', $where) . "
              GROUP BY c.id, c.fullname, c.shortname
              ORDER BY c.fullname ASC, c.id ASC";

        $countsql = "SELECT COUNT(1)
                       FROM (
                             SELECT c.id
                               FROM {course} c
                          LEFT JOIN {enrol} e ON e.courseid = c.id AND e.status = 0
                          LEFT JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.status = 0
                          LEFT JOIN {company_users} cu ON cu.userid = ue.userid
                              WHERE " . implode(' AND ', $where) . "
                           GROUP BY c.id
                            ) x";

        $rows = [];
        foreach ($DB->get_records_sql($sql, $params, $page * $perpage, $perpage) as $record) {
            $rows[] = [
                'courseid' => (int)$record->id,
                'fullname' => html_entity_decode(format_string((string)$record->fullname), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'shortname' => (string)$record->shortname,
                'enabled' => $this->course_enabled((int)$record->id),
            ];
        }

        return [
            'rows' => $rows,
            'totalcount' => (int)$DB->count_records_sql($countsql, $params),
            'page' => $page,
            'perpage' => $perpage,
        ];
    }

    public function case_counts(int $companyid): array {
        global $DB;

        $params = ['activewindow' => 1];
        $where = ['activewindow = :activewindow'];
        if ($companyid > 0) {
            $where[] = 'companyid = :companyid';
            $params['companyid'] = $companyid;
        }

        $sql = "SELECT workflowstatus, COUNT(1) AS totalcount
                  FROM {block_da_expcase}
                 WHERE " . implode(' AND ', $where) . "
              GROUP BY workflowstatus";

        $counts = [
            self::STATUS_AWAITING => 0,
            self::STATUS_REASSIGNED => 0,
            self::STATUS_DISMISSED => 0,
        ];
        foreach ($DB->get_records_sql($sql, $params) as $record) {
            $counts[(string)$record->workflowstatus] = (int)$record->totalcount;
        }

        return $counts;
    }

    public function list_cases(int $companyid, string $search = '', string $status = '', int $page = 0, int $perpage = 20): array {
        global $DB;

        $page = max(0, $page);
        $perpage = max(10, min(100, $perpage));
        $params = ['activewindow' => 1];
        $where = ['ec.activewindow = :activewindow'];

        if ($companyid > 0) {
            $where[] = 'ec.companyid = :companyid';
            $params['companyid'] = $companyid;
        }

        if ($status !== '' && in_array($status, [self::STATUS_AWAITING, self::STATUS_REASSIGNED, self::STATUS_DISMISSED], true)) {
            $where[] = 'ec.workflowstatus = :workflowstatus';
            $params['workflowstatus'] = $status;
        }

        if ($search !== '') {
            $like = '%' . $DB->sql_like_escape($search) . '%';
            $where[] = '('
                . $DB->sql_like('u.firstname', ':caseq1', false)
                . ' OR ' . $DB->sql_like('u.lastname', ':caseq2', false)
                . ' OR ' . $DB->sql_like('c.fullname', ':caseq3', false)
                . ')';
            $params['caseq1'] = $like;
            $params['caseq2'] = $like;
            $params['caseq3'] = $like;
        }

        $sql = "SELECT ec.id,
                       ec.userid,
                       ec.courseid,
                       ec.companyid,
                       ec.issuedate,
                       ec.expirydate,
                       ec.workflowstatus,
                       ec.cadencemode,
                       u.firstname,
                       u.lastname,
                       c.fullname AS coursename,
                       co.name AS companyname
                  FROM {block_da_expcase} ec
                  JOIN {user} u ON u.id = ec.userid
                  JOIN {course} c ON c.id = ec.courseid
             LEFT JOIN {company} co ON co.id = ec.companyid
                 WHERE " . implode(' AND ', $where) . "
              ORDER BY ec.expirydate ASC, u.lastname ASC, u.firstname ASC";

        $countsql = "SELECT COUNT(1)
                       FROM {block_da_expcase} ec
                       JOIN {user} u ON u.id = ec.userid
                       JOIN {course} c ON c.id = ec.courseid
                      WHERE " . implode(' AND ', $where);

        $rows = [];
        foreach ($DB->get_records_sql($sql, $params, $page * $perpage, $perpage) as $record) {
            $fullname = fullname($record);
            $rows[] = [
                'caseid' => (int)$record->id,
                'userid' => (int)$record->userid,
                'courseid' => (int)$record->courseid,
                'employee' => $fullname,
                'employeeprofile' => (new \moodle_url('/user/profile.php', ['id' => (int)$record->userid]))->out(false),
                'company' => trim((string)$record->companyname) !== '' ? format_string((string)$record->companyname) : get_string('label:unassigned', 'block_dashboardanalytics'),
                'course' => format_string((string)$record->coursename),
                'courserecordurl' => (new \moodle_url('/local/sentaldocupload/course_record.php', [
                    'courseid' => (int)$record->courseid,
                    'userid' => (int)$record->userid,
                ]))->out(false),
                'issuedate' => !empty($record->issuedate) ? userdate((int)$record->issuedate, get_string('strftimedate')) : '—',
                'expirydate' => !empty($record->expirydate) ? userdate((int)$record->expirydate, get_string('strftimedate')) : '—',
                'workflowstatus' => (string)$record->workflowstatus,
                'workflowstatuslabel' => $this->workflow_status_label((string)$record->workflowstatus),
                'cadencemode' => (string)$record->cadencemode,
            ];
        }

        return [
            'rows' => $rows,
            'totalcount' => (int)$DB->count_records_sql($countsql, $params),
            'page' => $page,
            'perpage' => $perpage,
        ];
    }

    public function take_action(int $caseid, string $action, int $actorid, array $options = []): array {
        global $DB;

        $case = $DB->get_record('block_da_expcase', ['id' => $caseid], '*', MUST_EXIST);
        if (!$this->can_manage_company($actorid, (int)$case->companyid)) {
            throw new \moodle_exception('error:noaccess', 'block_dashboardanalytics');
        }

        $now = time();
        $result = ['status' => true, 'message' => ''];

        if ($action === 'enroll') {
            $bridge = new recompletion_bridge();
            $reset = $bridge->reset_for_reassignment((int)$case->userid, (int)$case->courseid);
            if (!$reset['status']) {
                return [
                    'status' => false,
                    'message' => implode('; ', $reset['errors']),
                ];
            }

            $case->workflowstatus = self::STATUS_REASSIGNED;
            $case->reassignedat = $now;
            $case->actionby = $actorid;
            $case->timemodified = $now;
            $DB->update_record('block_da_expcase', $case);
            $this->audit((int)$case->id, 'reassigned', $actorid, [
                'courseid' => (int)$case->courseid,
                'userid' => (int)$case->userid,
            ]);
            $result['message'] = get_string('expiryworkflow:action:reassigned', 'block_dashboardanalytics');
            return $result;
        }

        if ($action === 'dismiss') {
            $case->workflowstatus = self::STATUS_DISMISSED;
            $case->dismissedat = $now;
            $case->actionby = $actorid;
            $case->timemodified = $now;
            $DB->update_record('block_da_expcase', $case);
            $this->audit((int)$case->id, 'dismissed', $actorid, []);
            $result['message'] = get_string('expiryworkflow:action:dismissed', 'block_dashboardanalytics');
            return $result;
        }

        if ($action === 'remind') {
            $cadence = (string)($options['cadence'] ?? self::CADENCE_WEEKLY);
            if (!in_array($cadence, [self::CADENCE_DAILY, self::CADENCE_EVERY3DAYS, self::CADENCE_WEEKLY], true)) {
                $cadence = self::CADENCE_WEEKLY;
            }

            $case->workflowstatus = self::STATUS_AWAITING;
            $case->cadencemode = $cadence;
            $case->nextnotifyat = $now + $this->cadence_seconds($cadence);
            $case->actionby = $actorid;
            $case->timemodified = $now;
            $DB->update_record('block_da_expcase', $case);
            $this->audit((int)$case->id, 'remind_later', $actorid, ['cadence' => $cadence]);
            $result['message'] = get_string('expiryworkflow:action:remindlater', 'block_dashboardanalytics');
            return $result;
        }

        throw new \moodle_exception('invalidparameter');
    }

    public function sync_cases(int $companyid = 0): array {
        global $DB;

        $now = time();
        $filters = ['statusmode' => 'course'];
        if ($companyid > 0) {
            $filters['companyids'] = [$companyid];
        }

        $rows = (new overview_repository())->enrolment_status_snapshot_rows($filters);
        $thresholdend = $now + ($this->threshold_days() * DAYSECS);
        $candidates = [];
        foreach ($rows as $row) {
            $expiry = (int)($row['expirytime'] ?? 0);
            $companyrowid = (int)($row['companyid'] ?? 0);
            if ($companyrowid <= 0 || $expiry <= $now || $expiry > $thresholdend) {
                continue;
            }

            $cyclekey = sha1((int)$row['userid'] . ':' . (int)$row['courseid'] . ':' . $expiry);
            $candidates[$cyclekey] = [
                'cyclekey' => $cyclekey,
                'userid' => (int)$row['userid'],
                'courseid' => (int)$row['courseid'],
                'companyid' => $companyrowid,
                'sourcekind' => (string)($row['sourcekind'] ?? 'unknown'),
                'sourceid' => (int)($row['documentid'] ?? 0),
                'issuedate' => (int)($row['issuedate'] ?? 0),
                'expirydate' => $expiry,
            ];
        }

        $params = [];
        $where = [];
        if ($companyid > 0) {
            $where[] = 'companyid = :companyid';
            $params['companyid'] = $companyid;
        }
        $existing = $DB->get_records_select('block_da_expcase', $where ? implode(' AND ', $where) : '', $params, '', '*', 0, 0);
        $existingbycycle = [];
        foreach ($existing as $record) {
            $existingbycycle[(string)$record->cyclekey] = $record;
        }

        $created = 0;
        $updated = 0;
        $deactivated = 0;

        foreach ($candidates as $cyclekey => $candidate) {
            if (!isset($existingbycycle[$cyclekey])) {
                $record = (object)($candidate + [
                    'activewindow' => 1,
                    'workflowstatus' => self::STATUS_AWAITING,
                    'cadencemode' => self::CADENCE_WEEKLY,
                    'nextnotifyat' => $now + $this->cadence_seconds(self::CADENCE_WEEKLY),
                    'lastnotifiedat' => 0,
                    'reassignedat' => 0,
                    'dismissedat' => 0,
                    'actionby' => 0,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
                $caseid = $DB->insert_record('block_da_expcase', $record);
                $this->audit((int)$caseid, 'tracked', 0, ['expirydate' => $candidate['expirydate']]);
                $created++;
                continue;
            }

            $record = $existingbycycle[$cyclekey];
            $record->companyid = $candidate['companyid'];
            $record->sourcekind = $candidate['sourcekind'];
            $record->sourceid = $candidate['sourceid'];
            $record->issuedate = $candidate['issuedate'];
            $record->expirydate = $candidate['expirydate'];
            $record->activewindow = 1;
            $record->timemodified = $now;
            $DB->update_record('block_da_expcase', $record);
            $updated++;
        }

        foreach ($existing as $record) {
            if (!isset($candidates[(string)$record->cyclekey]) && !empty($record->activewindow)) {
                $record->activewindow = 0;
                $record->timemodified = $now;
                $DB->update_record('block_da_expcase', $record);
                $deactivated++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'deactivated' => $deactivated,
            'candidatecount' => count($candidates),
        ];
    }

    public function send_due_digests(): array {
        global $DB;

        $now = time();
        $sent = 0;
        $recipientcount = 0;

        $sql = "SELECT ec.*, u.firstname, u.lastname, c.fullname AS coursename, co.name AS companyname
                  FROM {block_da_expcase} ec
                  JOIN {user} u ON u.id = ec.userid
                  JOIN {course} c ON c.id = ec.courseid
             LEFT JOIN {company} co ON co.id = ec.companyid
                 WHERE ec.activewindow = 1
                   AND ec.workflowstatus = :workflowstatus
                   AND ec.nextnotifyat > 0
                   AND ec.nextnotifyat <= :nextnotifyat
              ORDER BY ec.companyid ASC, ec.expirydate ASC";

        $cases = $DB->get_records_sql($sql, [
            'workflowstatus' => self::STATUS_AWAITING,
            'nextnotifyat' => $now,
        ]);

        $byrecipient = [];
        foreach ($cases as $case) {
            if (!$this->master_enabled() || !$this->company_config((int)$case->companyid)['enabled'] || !$this->course_enabled((int)$case->courseid)) {
                continue;
            }

            foreach ($this->recipients_for_company((int)$case->companyid) as $recipientkey => $recipient) {
                if (!isset($byrecipient[$recipientkey])) {
                    $byrecipient[$recipientkey] = [
                        'recipient' => $recipient,
                        'rows' => [],
                    ];
                }
                $byrecipient[$recipientkey]['rows'][] = $case;
            }
        }

        foreach ($byrecipient as $payload) {
            $recipient = $payload['recipient'];
            $rows = $payload['rows'];
            if (!$rows) {
                continue;
            }

            if ($this->send_digest_email($recipient, $rows)) {
                $recipientcount++;
                foreach ($rows as $case) {
                    $case->lastnotifiedat = $now;
                    $case->nextnotifyat = $now + $this->cadence_seconds((string)$case->cadencemode);
                    $case->timemodified = $now;
                    $DB->update_record('block_da_expcase', $case);
                    $this->audit((int)$case->id, 'digest_sent', 0, [
                        'recipient' => $recipient['email'],
                        'cadence' => (string)$case->cadencemode,
                    ]);
                    $sent++;
                }
            }
        }

        return [
            'casesent' => $sent,
            'recipients' => $recipientcount,
        ];
    }

    public function workflow_status_label(string $status): string {
        if ($status === self::STATUS_REASSIGNED) {
            return get_string('expiryworkflow:status:reassigned', 'block_dashboardanalytics');
        }
        if ($status === self::STATUS_DISMISSED) {
            return get_string('expiryworkflow:status:dismissed', 'block_dashboardanalytics');
        }
        return get_string('expiryworkflow:status:awaiting', 'block_dashboardanalytics');
    }

    public function cadence_options(): array {
        return [
            ['value' => self::CADENCE_DAILY, 'label' => get_string('expiryworkflow:cadence:daily', 'block_dashboardanalytics')],
            ['value' => self::CADENCE_EVERY3DAYS, 'label' => get_string('expiryworkflow:cadence:every3days', 'block_dashboardanalytics')],
            ['value' => self::CADENCE_WEEKLY, 'label' => get_string('expiryworkflow:cadence:weekly', 'block_dashboardanalytics')],
        ];
    }

    private function recipients_for_company(int $companyid): array {
        global $DB;

        $config = $this->company_config($companyid);
        $recipientids = $config['recipientids'];
        $recipients = [];

        if ($recipientids) {
            [$insql, $params] = $DB->get_in_or_equal($recipientids, SQL_PARAMS_NAMED, 'exprecipient');
            $sql = "SELECT id, firstname, lastname, email
                      FROM {user}
                     WHERE id {$insql}
                       AND deleted = 0
                       AND suspended = 0";
            foreach ($DB->get_records_sql($sql, $params) as $record) {
                $recipients['user:' . (int)$record->id] = [
                    'type' => 'user',
                    'id' => (int)$record->id,
                    'name' => fullname($record),
                    'email' => (string)$record->email,
                ];
            }
        }

        if (!$recipients && $this->default_recipient_email() !== '') {
            $recipients['email:' . $this->default_recipient_email()] = [
                'type' => 'email',
                'id' => 0,
                'name' => 'Training coordinator',
                'email' => $this->default_recipient_email(),
            ];
        }

        return $recipients;
    }

    private function send_digest_email(array $recipient, array $rows): bool {
        global $CFG;

        $subject = get_string('expiryworkflow:digest:subject', 'block_dashboardanalytics', format_string((string)($rows[0]->companyname ?? '')));
        $dashboardurl = (new \moodle_url('/blocks/dashboardanalytics/view.php'))->out(false);

        $htmlrows = '';
        $textrows = [];
        foreach ($rows as $row) {
            $employee = fullname($row);
            $course = format_string((string)$row->coursename);
            $company = trim((string)$row->companyname) !== '' ? format_string((string)$row->companyname) : get_string('label:unassigned', 'block_dashboardanalytics');
            $issuedate = !empty($row->issuedate) ? userdate((int)$row->issuedate, get_string('strftimedate')) : '—';
            $expirydate = !empty($row->expirydate) ? userdate((int)$row->expirydate, get_string('strftimedate')) : '—';

            $htmlrows .= '<tr><td>' . s($employee) . '</td><td>' . s($company) . '</td><td>' . s($course)
                . '</td><td>' . s($issuedate) . '</td><td>' . s($expirydate) . '</td>'
                . '<td><a href="' . s($dashboardurl) . '">' . s(get_string('expiryworkflow:digest:open', 'block_dashboardanalytics')) . '</a></td></tr>';

            $textrows[] = $employee . ' | ' . $company . ' | ' . $course . ' | ' . $issuedate . ' | ' . $expirydate;
        }

        $html = '<p>' . s(get_string('expiryworkflow:digest:intro', 'block_dashboardanalytics')) . '</p>'
            . '<table border="1" cellpadding="6" cellspacing="0"><thead><tr>'
            . '<th>' . s(get_string('label:employee', 'block_dashboardanalytics')) . '</th>'
            . '<th>' . s(get_string('label:company', 'block_dashboardanalytics')) . '</th>'
            . '<th>' . s(get_string('label:course', 'block_dashboardanalytics')) . '</th>'
            . '<th>' . s(get_string('label:completiondate', 'block_dashboardanalytics')) . '</th>'
            . '<th>' . s(get_string('label:expirydate', 'block_dashboardanalytics')) . '</th>'
            . '<th>' . s(get_string('label:action', 'block_dashboardanalytics')) . '</th>'
            . '</tr></thead><tbody>' . $htmlrows . '</tbody></table>'
            . '<p><a href="' . s($dashboardurl) . '">' . s(get_string('expiryworkflow:digest:opendashboard', 'block_dashboardanalytics')) . '</a></p>';

        $text = get_string('expiryworkflow:digest:intro', 'block_dashboardanalytics') . "\n\n"
            . implode("\n", $textrows) . "\n\n"
            . get_string('expiryworkflow:digest:opendashboard', 'block_dashboardanalytics') . ': ' . $dashboardurl;

        $from = get_admin();
        if ($recipient['type'] === 'user' && !empty($recipient['id'])) {
            $touser = \core_user::get_user((int)$recipient['id']);
            if (!$touser) {
                return false;
            }
            return email_to_user($touser, $from, $subject, $text, $html);
        }

        $touser = (object)[
            'id' => -1,
            'auth' => 'nologin',
            'confirmed' => 1,
            'policyagreed' => 1,
            'deleted' => 0,
            'suspended' => 0,
            'email' => (string)$recipient['email'],
            'firstname' => (string)($recipient['name'] ?: 'Training'),
            'lastname' => 'Coordinator',
            'maildisplay' => true,
            'mailformat' => 1,
            'maildigest' => 0,
            'mnethostid' => $CFG->mnet_localhost_id,
            'lang' => current_language(),
        ];
        return email_to_user($touser, $from, $subject, $text, $html);
    }

    private function recipient_company_ids_for_user(int $userid): array {
        global $DB;

        $ids = [];
        foreach ($DB->get_records('block_da_expcompany', null, '', 'companyid, recipientids') as $record) {
            if (in_array($userid, $this->csv_to_ints((string)$record->recipientids), true)) {
                $ids[] = (int)$record->companyid;
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));
        sort($ids);
        return $ids;
    }

    private function cadence_seconds(string $cadence): int {
        if ($cadence === self::CADENCE_DAILY) {
            return DAYSECS;
        }
        if ($cadence === self::CADENCE_EVERY3DAYS) {
            return 3 * DAYSECS;
        }
        return 7 * DAYSECS;
    }

    private function audit(int $caseid, string $action, int $actorid, array $detail): void {
        global $DB;

        $DB->insert_record('block_da_expaudit', (object)[
            'caseid' => $caseid,
            'action' => $action,
            'detail' => json_encode($detail),
            'actorid' => $actorid,
            'timecreated' => time(),
        ]);
    }

    private function csv_to_ints(string $csv): array {
        $items = preg_split('/\s*,\s*/', trim($csv), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $items = array_values(array_unique(array_map('intval', $items)));
        return array_values(array_filter($items, static fn(int $value): bool => $value > 0));
    }

    private function ints_to_csv(array $values): string {
        $values = array_values(array_unique(array_filter(array_map('intval', $values), static fn(int $value): bool => $value > 0)));
        sort($values);
        return implode(',', $values);
    }
}
