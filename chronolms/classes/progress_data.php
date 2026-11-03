<?php
// This file is part of the ChronoLMS theme.

/**
 * Real numbers for the Progress page: how the student is doing on coding activities (VPL and NextBlocks),
 * and how far they are through each topic (course section) of a course.
 *
 * Everything is read from Moodle's own tables (submissions, gradebook, completion); nothing is estimated.
 *
 * @package    theme_chronolms
 * @copyright  2026 Chronosphere Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_chronolms;

defined('MOODLE_INTERNAL') || die();

class progress_data {

    /**
     * Coding activity numbers across the given courses.
     *
     * @param \stdClass[] $courses courses to look at
     * @param int $userid student
     * @return array total (activities), tried (has a submission), average (0-100 or null), graded (count)
     */
    public static function coding_stats(array $courses, int $userid): array {
        global $DB;
        $out = ['total' => 0, 'tried' => 0, 'average' => null, 'graded' => 0];
        $ids = array_map(function ($c) {
            return (int)$c->id;
        }, array_values($courses));
        if (!$ids) {
            return $out;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'c');

        $out['total'] = (int)$DB->get_field_sql(
            "SELECT COUNT(1) FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module
              WHERE cm.course $insql AND cm.deletioninprogress = 0 AND cm.visible = 1 AND m.name IN ('vpl', 'nextblocks')",
            $inparams
        );

        $vpltried = (int)$DB->get_field_sql(
            "SELECT COUNT(DISTINCT cm.id)
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = 'vpl'
               JOIN {vpl_submissions} s ON s.vpl = cm.instance AND s.userid = :u
              WHERE cm.course $insql AND cm.deletioninprogress = 0",
            $inparams + ['u' => $userid]
        );
        $blockstried = (int)$DB->get_field_sql(
            "SELECT COUNT(DISTINCT cm.id)
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = 'nextblocks'
               JOIN {nextblocks_userdata} d ON d.nextblocksid = cm.instance AND d.userid = :u AND d.submissionnumber > 0
              WHERE cm.course $insql AND cm.deletioninprogress = 0",
            $inparams + ['u' => $userid]
        );
        $out['tried'] = $vpltried + $blockstried;

        // Average of the gradebook scores on coding activities that have been graded.
        $row = $DB->get_record_sql(
            "SELECT COUNT(1) AS n, AVG(gg.finalgrade / gi.grademax * 100) AS avg
               FROM {grade_grades} gg
               JOIN {grade_items} gi ON gi.id = gg.itemid
              WHERE gi.courseid $insql AND gi.itemtype = 'mod' AND gi.itemmodule IN ('vpl', 'nextblocks')
                AND gi.grademax > 0 AND gg.userid = :u AND gg.finalgrade IS NOT NULL",
            $inparams + ['u' => $userid]
        );
        if ($row && (int)$row->n > 0) {
            $out['graded'] = (int)$row->n;
            $out['average'] = (int)round(min(100, max(0, (float)$row->avg)));
        }
        return $out;
    }

    /**
     * Topics (course sections) of the course the student has made most progress in, unfinished ones first.
     * Only sections that have activities with completion tracking can show a percentage.
     *
     * @param array $coursesdata progress.php's per-course rows (id, fullname, completed_items)
     * @param \stdClass[] $courses enrolled courses keyed by id
     * @param int $userid student
     * @param int $limit most topics to return
     * @return array course (name), topics [name, done, total, pct]
     */
    public static function topic_mastery(array $coursesdata, array $courses, int $userid, int $limit = 6): array {
        $best = null;
        foreach ($coursesdata as $cd) {
            if ($best === null || $cd['completed_items'] > $best['completed_items']) {
                $best = $cd;
            }
        }
        if (!$best || !isset($courses[$best['id']])) {
            return ['course' => '', 'topics' => []];
        }
        $course = $courses[$best['id']];
        $modinfo = get_fast_modinfo($course, $userid);
        $completion = new \completion_info($course);
        if (!$completion->is_enabled()) {
            return ['course' => html_entity_decode(format_string($course->fullname), ENT_QUOTES), 'topics' => []];
        }

        $topics = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            if ((int)$section->section === 0 || !$section->uservisible) {
                continue;
            }
            $total = 0;
            $done = 0;
            foreach ($modinfo->get_cms() as $cm) {
                if ((int)$cm->sectionnum !== (int)$section->section || !$cm->uservisible
                        || $cm->completion == COMPLETION_TRACKING_NONE) {
                    continue;
                }
                $total++;
                $state = (int)$completion->get_data($cm, false, $userid)->completionstate;
                if ($state === COMPLETION_COMPLETE || $state === COMPLETION_COMPLETE_PASS) {
                    $done++;
                }
            }
            if ($total === 0) {
                continue;
            }
            $topics[] = [
                'name' => html_entity_decode(get_section_name($course, $section), ENT_QUOTES),
                'done' => $done,
                'total' => $total,
                'pct' => (int)round($done / $total * 100),
                'finished' => $done >= $total,
                'order' => (int)$section->section,
            ];
        }
        // Unfinished topics first (in course order), then finished ones.
        usort($topics, function ($a, $b) {
            return [$a['finished'], $a['order']] <=> [$b['finished'], $b['order']];
        });
        return ['course' => html_entity_decode(format_string($course->fullname), ENT_QUOTES), 'topics' => array_slice($topics, 0, $limit)];
    }
}
