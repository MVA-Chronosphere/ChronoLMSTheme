<?php
// This file is part of the ChronoLMS theme.

/**
 * Student engagement data for the dashboard: points, levels, the week strip,
 * class leaderboard and goal.
 *
 * Everything is derived from data Moodle already records (activity completions
 * and the standard log), so it needs no extra tables. Points:
 *   20 per completed activity, plus 5 per day the student was active.
 *
 * @package    theme_chronolms
 * @copyright  2026 Chronosphere Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_chronolms;

defined('MOODLE_INTERNAL') || die();

class gamification {

    /** Points per completed activity. */
    const POINTS_ACTIVITY = 20;
    /** Points per active day. */
    const POINTS_DAY = 5;
    /** Weekly class goal: completed activities per student. */
    const GOAL_PER_STUDENT = 5;

    /** Level thresholds (cumulative points) and titles. */
    const LEVELS = [
        1 => [0, 'Newcomer'],
        2 => [100, 'Explorer'],
        3 => [300, 'Code Explorer'],
        4 => [600, 'Bug Hunter'],
        5 => [1000, 'Logic Builder'],
        6 => [1500, 'Chrono Dev'],
    ];

    /** Activity types preferred for the daily challenge, best first. */
    const CHALLENGE_MODS = ['vpl', 'nextblocks', 'quiz', 'assign'];

    /** @var \DateTimeZone */
    private $tz;

    /** @var int user the data is built for */
    private $userid;

    public function __construct(int $userid) {
        global $USER;
        $this->userid = $userid;
        try {
            $this->tz = new \DateTimeZone(\core_date::get_user_timezone($USER));
        } catch (\Throwable $e) {
            $this->tz = new \DateTimeZone('UTC');
        }
    }

    /** @return int start of the current week (Monday 00:00) in the user's timezone */
    public function week_start(): int {
        return (new \DateTime('monday this week 00:00', $this->tz))->getTimestamp();
    }

    /**
     * Points for a set of users.
     *
     * @param int[] $userids
     * @param int $since only count activity at or after this time (0 = all time, last 365 days of activity)
     * @return int[] points keyed by user id
     */
    public function points_for(array $userids, int $since = 0): array {
        global $DB;
        $userids = array_values(array_unique(array_map('intval', $userids)));
        if (!$userids) {
            return [];
        }
        $points = array_fill_keys($userids, 0);

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        $rows = $DB->get_records_sql(
            "SELECT cmc.userid, COUNT(1) AS total
               FROM {course_modules_completion} cmc
               JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid AND cm.deletioninprogress = 0
               JOIN {modules} m ON m.id = cm.module AND m.name <> 'label'
              WHERE cmc.userid $insql AND cmc.completionstate IN (1, 2) AND cmc.timemodified >= :since
           GROUP BY cmc.userid",
            $inparams + ['since' => $since]
        );
        foreach ($rows as $r) {
            $points[(int)$r->userid] += (int)$r->total * self::POINTS_ACTIVITY;
        }

        foreach ($this->active_days($userids, $since ?: time() - 365 * DAYSECS) as $uid => $days) {
            $points[$uid] += $days * self::POINTS_DAY;
        }
        return $points;
    }

    /** Distinct active days per user since $since, using the user's timezone offset. */
    private function active_days(array $userids, int $since): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('logstore_standard_log')) {
            return [];
        }
        $offset = $this->tz->getOffset(new \DateTime('now', $this->tz));
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        $rows = $DB->get_records_sql(
            "SELECT userid, COUNT(DISTINCT FLOOR((timecreated + :off) / 86400)) AS days
               FROM {logstore_standard_log}
              WHERE userid $insql AND timecreated >= :since
           GROUP BY userid",
            $inparams + ['off' => $offset, 'since' => $since]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r->userid] = (int)$r->days;
        }
        return $out;
    }

    /** @return array level, title, points, cap, pct, remaining, nextlevel, nexttitle, ismax */
    public function level(int $points): array {
        $level = 1;
        foreach (self::LEVELS as $n => [$threshold]) {
            if ($points >= $threshold) {
                $level = $n;
            }
        }
        $ismax = !isset(self::LEVELS[$level + 1]);
        $floor = self::LEVELS[$level][0];
        $cap = $ismax ? max($points, $floor) : self::LEVELS[$level + 1][0];
        $span = max(1, $cap - $floor);
        return [
            'level' => $level,
            'title' => self::LEVELS[$level][1],
            'points' => $points,
            'cap' => $cap,
            'pct' => $ismax ? 100 : (int)floor(($points - $floor) / $span * 100),
            'remaining' => max(0, $cap - $points),
            'ismax' => $ismax,
            'nextlevel' => $ismax ? null : $level + 1,
            'nexttitle' => $ismax ? null : self::LEVELS[$level + 1][1],
        ];
    }

    /** @return array days (Monday to Sunday: done, today, future) and whether an activity was finished today */
    public function week_strip(): array {
        global $DB;
        $start = $this->week_start();
        $offset = $this->tz->getOffset(new \DateTime('now', $this->tz));
        $active = [];
        if ($DB->get_manager()->table_exists('logstore_standard_log')) {
            $rows = $DB->get_records_sql(
                "SELECT DISTINCT FLOOR((timecreated + :off) / 86400) AS d
                   FROM {logstore_standard_log} WHERE userid = :u AND timecreated >= :since",
                ['off' => $offset, 'u' => $this->userid, 'since' => $start]
            );
            foreach ($rows as $r) {
                $active[(int)$r->d] = true;
            }
        }
        $names = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];
        $todayidx = (int)(new \DateTime('now', $this->tz))->format('N') - 1;
        // Opening the dashboard always logs activity, so "today" is filled only by finishing an activity.
        $todaystart = (new \DateTime('today', $this->tz))->getTimestamp();
        $donetoday = $DB->record_exists_select(
            'course_modules_completion',
            'userid = :u AND completionstate IN (1, 2) AND timemodified >= :t',
            ['u' => $this->userid, 't' => $todaystart]
        );
        $strip = [];
        for ($i = 0; $i < 7; $i++) {
            $dayts = $start + $i * DAYSECS + 12 * HOURSECS;
            $key = (int)floor(($dayts + $offset) / 86400);
            $strip[] = [
                'label' => $names[$i],
                'done' => $i < $todayidx ? !empty($active[$key]) : ($i === $todayidx && $donetoday),
                'today' => $i === $todayidx,
                'future' => $i > $todayidx,
            ];
        }
        return [
            'days' => $strip,
            'doneToday' => $donetoday,
        ];
    }

    /** The student's class: the group they belong to in their most recent course. */
    public function my_group(): ?\stdClass {
        global $DB;
        return $DB->get_record_sql(
            "SELECT g.id, g.name, g.courseid
               FROM {groups} g
               JOIN {groups_members} gm ON gm.groupid = g.id AND gm.userid = :u
               JOIN {course} c ON c.id = g.courseid
          LEFT JOIN {user_lastaccess} la ON la.courseid = g.courseid AND la.userid = :u2
           ORDER BY la.timeaccess DESC, g.id DESC",
            ['u' => $this->userid, 'u2' => $this->userid],
            IGNORE_MULTIPLE
        ) ?: null;
    }

    /** How long a class's ranked points are reused, in seconds. Big classes are expensive to rank. */
    const BOARD_TTL = 600;

    /**
     * The student's class. A course group wins when they are in one; otherwise the smallest cohort they
     * belong to that is big enough to compete in (grade cohorts such as Class_9th, or a lab batch inside
     * one), ignoring staff cohorts.
     *
     * @return \stdClass|null kind (group|cohort), id, name, courseid
     */
    public function my_class(): ?\stdClass {
        global $DB;
        $group = $this->my_group();
        if ($group) {
            return (object)['kind' => 'group', 'id' => (int)$group->id, 'name' => $group->name, 'courseid' => (int)$group->courseid];
        }
        $rows = $DB->get_records_sql(
            "SELECT c.id, c.name, (SELECT COUNT(1) FROM {cohort_members} m WHERE m.cohortid = c.id) AS size
               FROM {cohort} c
               JOIN {cohort_members} cm ON cm.cohortid = c.id AND cm.userid = :u
              WHERE c.visible = 1",
            ['u' => $this->userid]
        );
        $best = null;
        foreach ($rows as $r) {
            if ($r->size < 5 || preg_match('/teacher|staff|admin|user/i', $r->name)) {
                continue;
            }
            if ($best === null || $r->size < $best->size) {
                $best = $r;
            }
        }
        if (!$best) {
            return null;
        }
        return (object)['kind' => 'cohort', 'id' => (int)$best->id, 'name' => trim(str_replace('_', ' ', $best->name)), 'courseid' => 0];
    }

    /** Students in a cohort: members who are active and not in a staff cohort. */
    private function cohort_members(\stdClass $class): array {
        global $DB;
        $like1 = $DB->sql_like('c2.name', ':l1', false);
        $like2 = $DB->sql_like('c2.name', ':l2', false);
        return $DB->get_records_sql(
            "SELECT u.id, u.firstname, u.lastname
               FROM {cohort_members} cm
               JOIN {user} u ON u.id = cm.userid AND u.deleted = 0 AND u.suspended = 0
              WHERE cm.cohortid = :c
                AND u.id NOT IN (SELECT cm2.userid FROM {cohort_members} cm2
                                   JOIN {cohort} c2 ON c2.id = cm2.cohortid
                                  WHERE $like1 OR $like2)",
            ['c' => $class->id, 'l1' => '%teacher%', 'l2' => '%staff%']
        );
    }

    /** Students in a class: group members who are not teachers in the course. */
    private function class_members(\stdClass $group): array {
        global $DB;
        $context = \context_course::instance($group->courseid);
        $members = $DB->get_records_sql(
            "SELECT u.id, u.firstname, u.lastname
               FROM {groups_members} gm
               JOIN {user} u ON u.id = gm.userid AND u.deleted = 0
              WHERE gm.groupid = :g",
            ['g' => $group->id]
        );
        $teachers = get_users_by_capability($context, 'moodle/course:update', 'u.id');
        return array_diff_key($members, $teachers);
    }

    /** Display name: first name and last initial only. */
    private function short_name(\stdClass $u): string {
        $last = \core_text::substr(trim($u->lastname), 0, 1);
        return trim($u->firstname . ($last !== '' ? ' ' . \core_text::strtoupper($last) . '.' : ''));
    }

    /**
     * Ranked list for one tab, trimmed to the top 3 plus the student and two classmates either side.
     *
     * @return array rows [rank, name, points, isme, iscrown, rankclass, gapbefore], plus 'next' text
     */
    private function board(array $members, array $points): array {
        arsort($points);
        $rows = [];
        $rank = 0;
        $me = null;
        foreach ($points as $uid => $pts) {
            $rank++;
            $rows[] = ['rank' => $rank, 'name' => $this->short_name($members[$uid]), 'points' => $pts,
                'isme' => $uid === $this->userid];
            if ($uid === $this->userid) {
                $me = $rank - 1;
            }
        }
        if ($me === null) {
            return ['rows' => [], 'next' => '', 'ranktext' => ''];
        }
        $show = [0, 1, 2];
        for ($i = max(3, $me - 2); $i <= min(count($rows) - 1, $me + 2); $i++) {
            $show[] = $i;
        }
        $show = array_values(array_unique(array_filter($show, fn($i) => $i < count($rows))));
        sort($show);
        $out = [];
        $last = -1;
        foreach ($show as $i) {
            $row = $rows[$i];
            $row['gapbefore'] = $last >= 0 && $i - $last > 1;
            $row['iscrown'] = $i === 0;
            $row['rankclass'] = $i < 3 ? 'r' . ($i + 1) : '';
            $row['pointsfmt'] = number_format($row['points']);
            $out[] = $row;
            $last = $i;
        }
        $next = '';
        if ($me > 0) {
            $need = $rows[$me - 1]['points'] - $rows[$me]['points'] + 1;
            $next = $need . ($need === 1 ? ' point' : ' points') . ' to pass ' . $rows[$me - 1]['name'];
        } else if (count($rows) > 1) {
            $next = 'You are top of the class';
        }
        $ranktext = count($rows) > 1 ? 'You are #' . ($me + 1) . ' of ' . number_format(count($rows)) : '';
        return ['rows' => $out, 'next' => $next, 'ranktext' => $ranktext];
    }

    /**
     * Ranked points and class-goal counts for a class, reused for BOARD_TTL seconds by everyone in it.
     *
     * @return array members (id => [firstname, lastname]), week, all, done (userid => activities this week)
     */
    private function class_numbers(\stdClass $class, int $weekstart): array {
        global $DB;
        $cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, 'theme_chronolms', 'classboard');
        $key = $class->kind . '_' . $class->id . '_' . $weekstart;
        $hit = $cache->get($key);
        if (is_array($hit) && ($hit['time'] ?? 0) > time() - self::BOARD_TTL && isset($hit['members'][$this->userid])) {
            return $hit;
        }

        $members = $class->kind === 'group' ? $this->class_members((object)['id' => $class->id, 'courseid' => $class->courseid])
                                            : $this->cohort_members($class);
        $ids = array_keys($members);
        $slim = [];
        foreach ($members as $id => $u) {
            $slim[(int)$id] = [$u->firstname, $u->lastname];
        }
        $done = [];
        if ($ids) {
            [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'u');
            $rows = $DB->get_records_sql(
                "SELECT cmc.userid, COUNT(1) AS total
                   FROM {course_modules_completion} cmc
                   JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid AND cm.deletioninprogress = 0
                   JOIN {modules} m ON m.id = cm.module AND m.name <> 'label'
                  WHERE cmc.userid $insql AND cmc.completionstate IN (1, 2) AND cmc.timemodified >= :since
               GROUP BY cmc.userid",
                $inparams + ['since' => $weekstart]
            );
            foreach ($rows as $r) {
                $done[(int)$r->userid] = (int)$r->total;
            }
        }
        $data = [
            'time' => time(),
            'members' => $slim,
            'week' => $this->points_for($ids, $weekstart),
            'all' => $this->points_for($ids, 0),
            'done' => $done,
        ];
        $cache->set($key, $data);
        return $data;
    }

    /**
     * Class leaderboard (this week and all time) and the weekly class goal.
     *
     * @return array|null null when the student has no class (no group and no suitable cohort)
     */
    public function class_data(): ?array {
        $class = $this->my_class();
        if (!$class) {
            return null;
        }
        $weekstart = $this->week_start();
        $nums = $this->class_numbers($class, $weekstart);
        if (!isset($nums['members'][$this->userid]) || count($nums['members']) < 2) {
            return null;
        }
        $members = [];
        foreach ($nums['members'] as $id => [$first, $last]) {
            $members[$id] = (object)['firstname' => $first, 'lastname' => $last];
        }

        // Class goal counts completed activities (not points), so it reads as "X of Y done".
        $classtotal = array_sum($nums['done']);
        $goal = max(5, (int)(ceil(count($members) * self::GOAL_PER_STUDENT / 5) * 5));

        $daysleft = 7 - (int)(new \DateTime('now', $this->tz))->format('N');
        return [
            'classname' => $class->name,
            'classsize' => count($members),
            'week' => $this->board($members, $nums['week']),
            'all' => $this->board($members, $nums['all']),
            'weekresets' => $daysleft === 0 ? 'Resets tomorrow' : 'Resets in ' . ($daysleft + 1) . ' days',
            'goal' => [
                'total' => number_format(min($classtotal, $goal)),
                'goal' => number_format($goal),
                'pct' => (int)min(100, round($classtotal / $goal * 100)),
                'mine' => $nums['done'][$this->userid] ?? 0,
                'endsday' => 'Sunday',
            ],
        ];
    }

    /**
     * First unfinished activity of a given type (vpl, nextblocks) across the student's courses, most
     * recently used course first. Falls back to the first one when everything is finished.
     *
     * @param \stdClass[] $courses enrolled courses, in the order to look
     * @param string $modname activity type
     * @return array|null [name, coursename, url]
     */
    public function practice_link(array $courses, string $modname): ?array {
        $fallback = null;
        foreach ($courses as $course) {
            if ($course->id == SITEID) {
                continue;
            }
            $modinfo = get_fast_modinfo($course, $this->userid);
            $completion = new \completion_info($course);
            foreach ($modinfo->get_instances_of($modname) as $cm) {
                if (!$cm->uservisible) {
                    continue;
                }
                $found = ['name' => format_string($cm->name), 'coursename' => format_string($course->fullname),
                    'url' => (new \moodle_url('/mod/' . $modname . '/view.php', ['id' => $cm->id]))->out(false)];
                $fallback = $fallback ?? $found;
                if (!$completion->is_enabled($cm)) {
                    return $found;
                }
                $state = (int)$completion->get_data($cm, false, $this->userid)->completionstate;
                if ($state !== COMPLETION_COMPLETE && $state !== COMPLETION_COMPLETE_PASS) {
                    return $found;
                }
            }
        }
        return $fallback;
    }

    /**
     * Today's challenge: an unfinished activity from the student's courses, the same one all day.
     * Practice activities (VPL, NextBlocks, quizzes) come first.
     */
    public function daily_challenge(array $courses): ?array {
        $candidates = [];
        $solvedtoday = null;
        $today = (new \DateTime('today', $this->tz))->getTimestamp();
        foreach ($courses as $course) {
            if ($course->id == SITEID) {
                continue;
            }
            $modinfo = get_fast_modinfo($course, $this->userid);
            $completion = new \completion_info($course);
            if (!$completion->is_enabled()) {
                continue;
            }
            foreach ($modinfo->get_cms() as $cm) {
                if (!$cm->uservisible || $cm->modname === 'label' || $cm->completion == COMPLETION_TRACKING_NONE) {
                    continue;
                }
                $data = $completion->get_data($cm, false, $this->userid);
                $complete = $data && in_array((int)$data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true);
                if ($complete) {
                    if ($data->timemodified >= $today && in_array($cm->modname, self::CHALLENGE_MODS, true)) {
                        $solvedtoday = $solvedtoday ?? $cm;
                    }
                    continue;
                }
                $rank = array_search($cm->modname, self::CHALLENGE_MODS, true);
                $candidates[] = [$rank === false ? 99 : $rank, $cm->id, $cm, $course];
            }
        }
        if (!$candidates && !$solvedtoday) {
            return null;
        }
        $pickcm = $solvedtoday;
        $pickcourse = null;
        if (!$pickcm) {
            usort($candidates, fn($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
            $bestrank = $candidates[0][0];
            $pool = array_values(array_filter($candidates, fn($c) => $c[0] === $bestrank));
            $dayindex = (int)(new \DateTime('now', $this->tz))->format('z');
            [, , $pickcm, $pickcourse] = $pool[$dayindex % count($pool)];
        }
        return [
            'name' => format_string($pickcm->name),
            'coursename' => $pickcourse ? format_string($pickcourse->shortname) : '',
            'type' => get_string('modulename', $pickcm->modname),
            'url' => $pickcm->url ? $pickcm->url->out(false) : (new \moodle_url('/course/view.php', ['id' => $pickcm->course]))->out(false),
            'solved' => $solvedtoday !== null,
            'points' => self::POINTS_ACTIVITY + self::POINTS_DAY,
        ];
    }

    /**
     * The next unfinished activity in a course, in course order, and how many remain.
     *
     * @return array|null [name, url, remaining]
     */
    public function next_activity(\stdClass $course): ?array {
        $completion = new \completion_info($course);
        if (!$completion->is_enabled()) {
            return null;
        }
        $modinfo = get_fast_modinfo($course, $this->userid);
        $next = null;
        $remaining = 0;
        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible || $cm->modname === 'label' || $cm->completion == COMPLETION_TRACKING_NONE) {
                continue;
            }
            $data = $completion->get_data($cm, false, $this->userid);
            if ($data && in_array((int)$data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true)) {
                continue;
            }
            $remaining++;
            $next = $next ?? $cm;
        }
        if (!$next) {
            return null;
        }
        return [
            'name' => format_string($next->name),
            'url' => $next->url ? $next->url->out(false) : (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'remaining' => $remaining,
        ];
    }
}
