<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Thin wrapper around local_recompletion so dashboardanalytics can trigger
 * audited reassign/reset actions without duplicating completion reset logic.
 */
class recompletion_bridge {
    /**
     * Reset a user in a course for reassignment and keep archive history.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $companyid
     * @return array{status:bool, errors:string[]}
     */
    public function reset_for_reassignment(int $userid, int $courseid, int $companyid): array {
        global $CFG;

        require_once($CFG->dirroot . '/local/recompletion/locallib.php');

        $course = get_course($courseid);
        $config = $this->synthetic_config($courseid);

        $task = new \local_recompletion\task\check_recompletion();
        $errors = $task->reset_user($userid, $course, $config);

        $this->ensure_active_enrolment($userid, $courseid);
        if (empty($errors)) {
            $this->reset_iomad_tracking($userid, $courseid, $companyid);
        }

        return [
            'status' => empty($errors),
            'errors' => array_values(array_filter(array_map('strval', $errors))),
        ];
    }

    /**
     * Synchronise IOMAD's current course state with the Moodle completion reset.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $companyid
     * @return void
     */
    private function reset_iomad_tracking(int $userid, int $courseid, int $companyid): void {
        global $DB;

        $records = $DB->get_records('local_iomad_track', [
            'userid' => $userid,
            'courseid' => $courseid,
            'companyid' => $companyid,
            'coursecleared' => 0,
        ], 'id DESC', '*', 0, 1);

        if (!$records) {
            return;
        }

        $record = reset($records);
        $record->timestarted = null;
        $record->timecompleted = null;
        $record->timeexpires = null;
        $record->finalscore = 0;
        $record->modifiedtime = time();
        $DB->update_record('local_iomad_track', $record);
    }

    /**
     * Create a synthetic recompletion config so we can reuse the recompletion
     * engine without requiring every course to be manually configured first.
     *
     * @param int $courseid
     * @return \stdClass
     */
    private function synthetic_config(int $courseid): \stdClass {
        $config = (object)[
            'course' => $courseid,
            'recompletiontype' => 'ondemand',
            'archivecompletiondata' => 1,
            'deletegradedata' => 1,
            'recompletionnotify' => '',
            'recompletionunenrolenable' => 0,
            'recompletionemailbody' => '',
            'recompletionemailbody_format' => FORMAT_HTML,
            'recompletionemailsubject' => '',
            'nextresettime' => 0,
        ];

        $deleteplugins = [
            'quiz' => ['archivequiz' => 1],
            'scorm' => ['archivescorm' => 1],
            'h5pactivity' => ['archiveh5pactivity' => 1],
            'choice' => ['archivechoice' => 1],
            'lesson' => ['archivelesson' => 1],
            'questionnaire' => ['archivequestionnaire' => 1],
            'lti' => ['archivelti' => 1],
            'customcert' => ['archivecustomcert' => 1],
            'certificate' => ['archivecertificate' => 1],
            'coursecertificate' => ['archivecoursecertificate' => 1],
            'hotpot' => ['archivehotpot' => 1],
            'hvp' => ['archivehvp' => 1],
            'pulse' => ['archivepulse' => 1],
        ];

        foreach ($deleteplugins as $plugin => $extraflags) {
            $config->{$plugin} = LOCAL_RECOMPLETION_DELETE;
            foreach ($extraflags as $flag => $value) {
                $config->{$flag} = $value;
            }
        }

        return $config;
    }

    /**
     * Best-effort active enrolment enforcement for reassignment.
     *
     * @param int $userid
     * @param int $courseid
     * @return void
     */
    private function ensure_active_enrolment(int $userid, int $courseid): void {
        if (is_enrolled(\context_course::instance($courseid), $userid, '', true)) {
            return;
        }

        foreach (enrol_get_instances($courseid, true) as $instance) {
            if ((int)$instance->status !== ENROL_INSTANCE_ENABLED) {
                continue;
            }

            $plugin = enrol_get_plugin($instance->enrol);
            if (!$plugin) {
                continue;
            }

            try {
                $plugin->enrol_user($instance, $userid, (int)$instance->roleid ?: 0, time(), 0, ENROL_USER_ACTIVE);
                return;
            } catch (\Throwable $e) {
                continue;
            }
        }
    }
}
