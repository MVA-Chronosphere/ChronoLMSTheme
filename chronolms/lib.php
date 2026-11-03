<?php
/**
 * ChronoLMS Theme - lib functions.
 *
 * @package    theme_chronolms
 * @copyright  2026 Chronosphere Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Returns the pre-SCSS (variables) injected before the main SCSS.
 */
function theme_chronolms_get_pre_scss($theme) {
    $scss = '';

    // Inject any admin-customised colour overrides here
    $navycolor = !empty($theme->settings->navycolor) ? $theme->settings->navycolor : '#0a1a3e';
    $bluecolor  = !empty($theme->settings->primarycolor) ? $theme->settings->primarycolor : '#1565c0';

    $scss .= '$clr-navy-900: ' . $navycolor . ";\n";
    $scss .= '$clr-blue-600: ' . $bluecolor . ";\n";

    return $scss;
}

/**
 * Returns the main SCSS content for the theme.
 */
function theme_chronolms_get_main_scss_content($theme) {
    global $CFG;

    $scss = '';

    // Load precompiled CSS directly for bulletproof reliability
    $csspath = $CFG->dirroot . '/theme/chronolms/style/chronolms.css';
    if (file_exists($csspath)) {
        $scss .= file_get_contents($csspath);
    }

    return $scss;
}

/**
 * Returns extra SCSS injected after main SCSS.
 */
function theme_chronolms_get_extra_scss($theme) {
    $scss = '';
    if (!empty($theme->settings->extracss)) {
        $scss .= $theme->settings->extracss;
    }
    return $scss;
}

/**
 * Registers the theme's dependency-free browser behaviour.
 */
function theme_chronolms_setup_page(): void {
    global $PAGE, $SESSION;

    if (!empty($PAGE->course) && $PAGE->course->id > 1) {
        $SESSION->chronolms_active_course_id = (int)$PAGE->course->id;
    }

    $PAGE->requires->js(new moodle_url('/theme/chronolms/javascript/app.js'));

    // chronolms-bootstrap: Moodle's Bootstrap JS (dropdowns, action menus, modals)
    // is not loaded by the custom layouts. Load it on every page and expose it as
    // window.bootstrap so app.js skips its manual "More" menu toggle.
    // Coding lab pages (VPL, NextBlocks) are the exception: their editors use jQuery UI widgets that
    // break when Bootstrap's script is on the page too (the VPL editor never initialises). They stay
    // without it, as they were before; app.js gives them a simple dropdown toggle instead.
    if (strpos($PAGE->pagetype, 'mod-vpl') !== 0 && strpos($PAGE->pagetype, 'mod-nextblocks') !== 0) {
        $PAGE->requires->js_amd_inline(
            "require(['theme_boost/loader', 'theme_boost/index'], function(loader, bootstrap) {" .
            " window.bootstrap = window.bootstrap || bootstrap; });"
        );
    }
}

/**
 * Renders configured Moodle blocks so integrations remain available to JS.
 *
 * @return string
 */
function theme_chronolms_render_plugin_blocks(): string {
    global $OUTPUT, $PAGE;

    if (!$PAGE->blocks->is_known_region('side-pre')) {
        return '';
    }

    return $OUTPUT->blocks('side-pre');
}

/**
 * Renders Moodle secondary navigation (tabs/moremenu) safely for admin, course, and report pages.
 *
 * @return string
 */
function theme_chronolms_get_secondary_navigation(): string {
    global $PAGE, $OUTPUT;

    try {
        if ($PAGE->has_secondary_navigation()) {
            $tablistnav = $PAGE->has_tablist_secondary_navigation();
            $moremenu = new \core\navigation\output\more_menu($PAGE->secondarynav, 'nav-tabs', true, $tablistnav);
            $secondarynavigation = $moremenu->export_for_template($OUTPUT);
            if (!empty($secondarynavigation)) {
                return '<div class="secondary-navigation d-print-none mb-3">' .
                    $OUTPUT->render_from_template('core/moremenu', $secondarynavigation) .
                    '</div>';
            }
        }
    } catch (\Throwable $e) {
        // Fallback gracefully without breaking page rendering
    }

    return '';
}

/**
 * Serves theme files (fonts, images, etc.)
 */
function theme_chronolms_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel == CONTEXT_SYSTEM && ($filearea === 'logo' || $filearea === 'backgroundimage')) {
        $theme = theme_config::load('chronolms');
        if ($filearea === 'logo') {
            return $theme->setting_file_serve('logo', $args, $forcedownload, $options);
        } else if ($filearea === 'backgroundimage') {
            return $theme->setting_file_serve('backgroundimage', $args, $forcedownload, $options);
        }
    }
    send_file_not_found();
}

/**
 * Determine if the current user is an administrator or educator/teacher.
 *
 * @param int $courseid Optional course ID context.
 * @return bool True if staff/educator/admin, false for student.
 */
function theme_chronolms_is_staff($courseid = 0): bool {
    global $USER, $DB;
    if (!isloggedin() || isguestuser()) {
        return false;
    }
    if (is_siteadmin()) {
        return true;
    }
    if ($courseid > 1) {
        $coursecontext = context_course::instance($courseid, IGNORE_MISSING);
        if ($coursecontext && (has_capability('moodle/grade:viewall', $coursecontext) || has_capability('moodle/course:update', $coursecontext))) {
            return true;
        }
    }
    $syscontext = context_system::instance();
    if (has_capability('moodle/grade:viewall', $syscontext) || has_capability('moodle/site:config', $syscontext)) {
        return true;
    }

    // Check if user has teacher or manager role assignment in any course.
    static $user_is_teacher = null;
    if ($user_is_teacher === null) {
        $sql = "SELECT COUNT(ra.id)
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE ra.userid = :userid
                   AND r.shortname IN ('manager', 'coursecreator', 'editingteacher', 'teacher')";
        try {
            $count = $DB->count_records_sql($sql, ['userid' => $USER->id]);
            $user_is_teacher = ($count > 0);
        } catch (\Throwable $e) {
            $user_is_teacher = false;
        }
    }
    return (bool)$user_is_teacher;
}

/**
 * Get the sidebar navigation items for the current user.
 *
 * @return array Array of nav item objects.
 */
function theme_chronolms_get_sidebar_nav(): array {
    global $CFG, $PAGE, $USER, $DB, $SESSION, $course;

    $path = $PAGE->url ? $PAGE->url->get_path() : '';
    $query = $PAGE->url ? $PAGE->url->get_query_string() : '';

    // Determine the active or default course ID.
    $courseid = null;
    if (!empty($course) && !empty($course->id) && $course->id > 1) {
        $courseid = (int)$course->id;
        $SESSION->chronolms_active_course_id = $courseid;
    } else if (!empty($PAGE->course) && !empty($PAGE->course->id) && $PAGE->course->id > 1) {
        $courseid = (int)$PAGE->course->id;
        $SESSION->chronolms_active_course_id = $courseid;
    } else if (!empty($PAGE->cm) && !empty($PAGE->cm->course) && $PAGE->cm->course > 1) {
        $courseid = (int)$PAGE->cm->course;
        $SESSION->chronolms_active_course_id = $courseid;
    } else {
        $urlcourseid = optional_param('id', 0, PARAM_INT);
        $urlcourseparam = optional_param('course', 0, PARAM_INT);
        $cid = $urlcourseparam ?: $urlcourseid;
        if ($cid > 1 && (strpos($path, '/course/') !== false || strpos($path, '/mod/') !== false)) {
            $courseid = (int)$cid;
            $SESSION->chronolms_active_course_id = $courseid;
        } else if (!empty($SESSION->chronolms_active_course_id)) {
            $courseid = (int)$SESSION->chronolms_active_course_id;
        }
    }

    // Only an explicitly viewed course (or the last selection) drives Coding Lab.
    $codingcourseid = $courseid;

    if (!$courseid && isloggedin() && !isguestuser()) {
        $mycourses = enrol_get_my_courses();
        if (!empty($mycourses)) {
            $first = reset($mycourses);
            $courseid = $first->id;
        }
    }
    // Fallback course ID if none enrolled (e.g. admin or new user)
    if (!$courseid) {
        try {
            $firstcourse = $DB->get_record_select('course', 'id > 1 AND visible = 1', null, 'id', 'id', 0, 1);
            $courseid = $firstcourse ? $firstcourse->id : 4;
        } catch (Throwable $e) {
            $courseid = 4;
        }
    }

    // Determine Coding Lab URL dynamically based on course activities: NextBlocks vs VPL
    $codinglab_url = $codingcourseid
        ? new moodle_url('/course/view.php', ['id' => $codingcourseid])
        : new moodle_url('/my/courses.php');
    if ($codingcourseid) {
        try {
            $courseobj = ($PAGE->course && $PAGE->course->id == $codingcourseid) ? $PAGE->course : get_course($codingcourseid);
            $modinfo = get_fast_modinfo($courseobj);

            $first_nextblocks_cm = null;
            $has_vpl = false;

            foreach ($modinfo->get_cms() as $cm) {
                if (!$cm->uservisible) {
                    continue;
                }
                if ($cm->modname === 'nextblocks') {
                    if (!$first_nextblocks_cm) {
                        $first_nextblocks_cm = $cm;
                    }
                } else if ($cm->modname === 'vpl') {
                    $has_vpl = true;
                }
            }

            if ($first_nextblocks_cm && !$has_vpl) {
                // Course is NextBlocks-based (e.g. Block Coding).
                $target_cm = $first_nextblocks_cm;
                try {
                    if (class_exists('completion_info')) {
                        $completion = new completion_info($courseobj);
                        if ($completion->is_enabled()) {
                            foreach ($modinfo->get_cms() as $cm) {
                                if ($cm->uservisible && $cm->modname === 'nextblocks') {
                                    $data = $completion->get_data($cm, false, $USER->id);
                                    if (!$data || ($data->completionstate != COMPLETION_COMPLETE && $data->completionstate != COMPLETION_COMPLETE_PASS)) {
                                        $target_cm = $cm;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                } catch (\Throwable $e_comp) {
                    $target_cm = $first_nextblocks_cm;
                }
                $codinglab_url = new moodle_url('/mod/nextblocks/view.php', ['id' => $target_cm->id]);
            } else if ($has_vpl) {
                // Course is VPL-based (Python Advanced, Foundations, Intermediate).
                $codinglab_url = new moodle_url('/mod/vpl/index.php', ['id' => $codingcourseid]);
            } else if ($first_nextblocks_cm) {
                $codinglab_url = new moodle_url('/mod/nextblocks/view.php', ['id' => $first_nextblocks_cm->id]);
            }
        } catch (\Throwable $e) {
            $codinglab_url = new moodle_url('/my/courses.php');
        }
    }

    $is_mycourses    = (strpos($path, '/my/courses.php') !== false || strpos($path, '/course/index.php') !== false || strpos($path, '/course/view.php') !== false);
    $is_dashboard    = (($PAGE->pagetype === 'my-index' || $path === '/my/' || $path === '/my/index.php') && !$is_mycourses);
    $is_learningpath = (strpos($path, '/theme/chronolms/learningpath.php') !== false);
    $is_course       = ((strpos($path, '/course/view.php') !== false || strpos($PAGE->pagetype, 'course-view') === 0) && !$is_learningpath);
    $is_quiz         = (strpos($path, '/theme/chronolms/assessments.php') !== false || strpos($path, '/mod/quiz') !== false || (strpos($path, '/course/overview.php') !== false && (strpos($query, 'quiz') !== false || !empty($_GET['expand']))));
    $is_codinglab    = (strpos($path, '/mod/vpl') !== false || strpos($path, '/mod/nextblocks') !== false);
    $is_progress     = (
        strpos($path, '/local/code_analytics') !== false ||
        strpos($path, '/theme/chronolms/progress.php') !== false ||
        strpos($path, '/grade') !== false ||
        strpos($path, '/report/progress') !== false ||
        strpos($path, '/report/outline') !== false
    );
    $is_certificates = (
        strpos($path, '/mod/customcert') !== false ||
        strpos($path, '/badges') !== false
    );
    $is_calendar     = (strpos($path, '/calendar') !== false);
    $is_admin        = (strpos($path, '/admin') !== false);

    // Role-aware Progress link:
    // Educators & Admins -> Code Analytics (local_code_analytics)
    // Students -> Personalized Progress & Learning Journey Dashboard (theme/chronolms/progress.php)
    $is_staff = theme_chronolms_is_staff($courseid);
    $has_analytics = file_exists($CFG->dirroot . '/local/code_analytics/index.php');

    if ($is_staff && $has_analytics) {
        if ($courseid > 1 && (!is_siteadmin() || $is_course)) {
            $progress_url = new moodle_url('/local/code_analytics/index.php', ['courseid' => $courseid]);
        } else {
            $progress_url = new moodle_url('/local/code_analytics/index.php');
        }
    } else {
        if (file_exists($CFG->dirroot . '/theme/chronolms/progress.php')) {
            $progress_url = new moodle_url('/theme/chronolms/progress.php', ($is_course && $courseid > 1) ? ['id' => $courseid] : []);
        } else {
            $progress_url = new moodle_url('/grade/report/overview/index.php');
        }
    }

    $has_customcert = file_exists($CFG->dirroot . '/mod/customcert/my_certificates.php');
    $certificates_url = $has_customcert
        ? new moodle_url('/mod/customcert/my_certificates.php')
        : new moodle_url('/badges/mybadges.php');

    $navitems = [
        [
            'id'        => 'dashboard',
            'label'     => get_string('myhome'),
            'icon'      => 'dashboard',
            'dashboard' => true,
            'url'       => new moodle_url('/my/'),
            'active'    => $is_dashboard,
        ],
        [
            'id'      => 'mycourses',
            'label'   => get_string('mycourses'),
            'icon'    => 'courses',
            'courses' => true,
            'url'     => new moodle_url('/my/courses.php'),
            'active'  => $is_mycourses,
        ],
        [
            'id'     => 'learningpath',
            'label'  => get_string('learningpath', 'theme_chronolms'),
            'icon'   => 'path',
            'path'   => true,
            'url'    => new moodle_url('/theme/chronolms/learningpath.php'),
            'active' => $is_learningpath,
        ],
        [
            'id'     => 'tests',
            'label'  => get_string('testsassessments', 'theme_chronolms'),
            'icon'   => 'tests',
            'tests'  => true,
            'url'    => new moodle_url('/theme/chronolms/assessments.php'),
            'active' => $is_quiz,
        ],
        [
            'id'     => 'codinglab',
            'label'  => get_string('codinglab', 'theme_chronolms'),
            'icon'   => 'code',
            'code'   => true,
            'url'    => $codinglab_url,
            'active' => $is_codinglab,
        ],
        [
            'id'       => 'progress',
            'label'    => get_string('progress', 'theme_chronolms'),
            'icon'     => 'progress',
            'progress' => true,
            'url'      => $progress_url,
            'active'   => $is_progress,
        ],
        [
            'id'          => 'certificates',
            'label'       => get_string('certificates', 'theme_chronolms'),
            'icon'        => 'certificate',
            'certificate' => true,
            'url'         => $certificates_url,
            'active'      => $is_certificates,
        ],
        [
            'id'       => 'calendar',
            'label'    => get_string('calendar', 'core_calendar'),
            'icon'     => 'calendar',
            'calendar' => true,
            'url'      => new moodle_url('/calendar/view.php'),
            'active'   => $is_calendar,
        ],
        [
            'id'     => 'help',
            'label'  => get_string('help'),
            'icon'   => 'help',
            'help'   => true,
            'url'    => '#',
            'active' => false,
            'class'  => 'chatbot-trigger',
        ],
    ];

    // If site administrator, give direct navigation item for site administration.
    if (is_siteadmin()) {
        $navitems[] = [
            'id'     => 'admin',
            'label'  => get_string('siteadministration', 'theme_chronolms'),
            'icon'   => 'admin',
            'admin'  => true,
            'url'    => new moodle_url('/admin/search.php'),
            'active' => $is_admin,
        ];
    }

    return $navitems;
}

/**
 * Get streak data for the current user.
 * Uses local_streak if installed, otherwise calculates consecutive daily activity
 * from standard Moodle logs natively.
 *
 * @param int $userid
 * @return array ['count' => int, 'active' => bool]
 */
function theme_chronolms_get_streak(int $userid): array {
    global $DB, $USER;

    static $streakcache = [];
    if (isset($streakcache[$userid])) {
        return $streakcache[$userid];
    }

    // 1. Delegate to local_streak plugin if installed.
    if (core_plugin_manager::instance()->get_plugin_info('local_streak')) {
        $result = \local_streak\api::get_streak($userid);
        $streakcache[$userid] = $result;
        return $result;
    }

    if ($userid <= 0) {
        return ['count' => 0, 'active' => false];
    }

    // 2. Native calculation from Moodle standard log store.
    try {
        $tz = core_date::get_user_timezone($USER);
        $dtzone = new DateTimeZone($tz);
    } catch (\Throwable $e) {
        $dtzone = new DateTimeZone('UTC');
    }

    $today = new DateTime('now', $dtzone);
    $todaystr = $today->format('Y-m-d');

    $activedates = [];
    // User is active today since they are currently loading the page.
    $activedates[$todaystr] = true;

    $lookback = strtotime('-60 days');

    try {
        if ($DB->get_manager()->table_exists('logstore_standard_log')) {
            $records = $DB->get_records_select(
                'logstore_standard_log',
                'userid = :userid AND timecreated >= :since',
                ['userid' => $userid, 'since' => $lookback],
                'timecreated DESC',
                'id, timecreated',
                0,
                2000
            );

            foreach ($records as $r) {
                try {
                    $dt = new DateTime('@' . $r->timecreated);
                    $dt->setTimezone($dtzone);
                    $activedates[$dt->format('Y-m-d')] = true;
                } catch (\Throwable $e) {
                    // Ignore malformed timestamps.
                }
            }
        }
    } catch (\Throwable $e) {
        // Table or query issue - proceed with active today.
    }

    // Count consecutive days backward starting from today.
    $streak = 0;
    $cursor = clone $today;

    while (true) {
        $datestr = $cursor->format('Y-m-d');
        if (!empty($activedates[$datestr])) {
            $streak++;
            $cursor->modify('-1 day');
        } else {
            break;
        }
    }

    $result = [
        'count'  => $streak,
        'active' => ($streak >= 1),
    ];

    $streakcache[$userid] = $result;
    return $result;
}

/**
 * Builds the template data for rendering theme_chronolms/nav/sidebar.
 *
 * @param array $extras Additional context values.
 * @return array
 */
function theme_chronolms_get_codinglab_tree(?int $courseid = null): array {
    global $DB, $USER, $PAGE, $COURSE;

    $cmid = optional_param('id', 0, PARAM_INT);

    // Auto-detect course ID if not passed
    if (empty($courseid) || $courseid <= 1) {
        if (!empty($PAGE->course) && $PAGE->course->id > 1) {
            $courseid = (int)$PAGE->course->id;
        } else if (!empty($COURSE) && $COURSE->id > 1) {
            $courseid = (int)$COURSE->id;
        } else if ($cmid > 0) {
            try {
                $cmrecord = $DB->get_record('course_modules', ['id' => $cmid], 'course');
                if ($cmrecord) {
                    $courseid = (int)$cmrecord->course;
                }
            } catch (\Throwable $e) {}
        }
    }

    if (empty($courseid) || $courseid <= 1) {
        try {
            $enrolled = enrol_get_all_users_courses($USER->id, true);
            if (!empty($enrolled)) {
                $first = reset($enrolled);
                $courseid = (int)$first->id;
            }
        } catch (\Throwable $e) {}
    }

    if (empty($courseid) || $courseid <= 1) {
        return ['has_tree' => false, 'levels' => []];
    }

    try {
        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            return ['has_tree' => false, 'levels' => []];
        }

        $modinfo = get_fast_modinfo($course);
        $sections = $modinfo->get_section_info_all();
        $completion = new completion_info($course);

        $levels = [];
        $levelidx = 0;
        $hascurrentfound = false;

        foreach ($sections as $sectionnum => $secinfo) {
            if (empty($secinfo->sequence)) {
                continue;
            }

            $cms_in_sec = [];
            $cmids = explode(',', $secinfo->sequence);

            foreach ($cmids as $sec_cmid) {
                $sec_cmid = (int)trim($sec_cmid);
                if ($sec_cmid <= 0 || !isset($modinfo->cms[$sec_cmid])) {
                    continue;
                }
                $cm = $modinfo->cms[$sec_cmid];

                // Filter for coding activities (vpl, nextblocks, assign)
                if (!in_array($cm->modname, ['vpl', 'nextblocks', 'assign'])) {
                    continue;
                }
                if (!$cm->uservisible) {
                    continue;
                }

                $is_current = ($cmid > 0 && $cmid === (int)$cm->id);
                if ($is_current) {
                    $hascurrentfound = true;
                }

                $is_completed = false;
                if ($completion->is_enabled($cm)) {
                    $compdata = $completion->get_data($cm, false, $USER->id);
                    $is_completed = ($compdata && ($compdata->completionstate == COMPLETION_COMPLETE || $compdata->completionstate == COMPLETION_COMPLETE_PASS));
                }

                $targeturl = $cm->url ? $cm->url->out(false) : '';
                if ($cm->modname === 'vpl') {
                    $targeturl = (new moodle_url('/mod/vpl/forms/edit.php', ['id' => $cm->id]))->out(false);
                }

                $cms_in_sec[] = [
                    'id'           => $cm->id,
                    'name'         => html_entity_decode(format_string($cm->name), ENT_QUOTES, 'UTF-8'),
                    'modname'      => $cm->modname,
                    'is_vpl'       => ($cm->modname === 'vpl'),
                    'url'          => $targeturl,
                    'is_completed' => $is_completed,
                    'is_current'   => $is_current,
                ];
            }

            if (!empty($cms_in_sec)) {
                $rawtitle = get_section_name($course, $secinfo);
                $title = html_entity_decode(trim(format_string($rawtitle)), ENT_QUOTES, 'UTF-8');
                if (empty($title) || $title === "Topic {$sectionnum}" || $title === "General") {
                    $title = "Level " . $levelidx;
                }

                $is_active_level = false;
                foreach ($cms_in_sec as $c) {
                    if ($c['is_current']) {
                        $is_active_level = true;
                        break;
                    }
                }

                $levels[] = [
                    'level_index'     => $levelidx,
                    'section_num'     => $sectionnum,
                    'level_title'     => $title,
                    'is_active_level' => $is_active_level,
                    'is_expanded'     => $is_active_level || ($levelidx === 0 && !$hascurrentfound),
                    'programs'        => $cms_in_sec,
                    'program_count'   => count($cms_in_sec),
                ];
                $levelidx++;
            }
        }

        if (!$hascurrentfound && !empty($levels)) {
            $levels[0]['is_active_level'] = true;
            $levels[0]['is_expanded'] = true;
        }

        return [
            'has_tree'    => !empty($levels),
            'courseid'    => $courseid,
            'coursename'  => format_string($course->fullname),
            'levels'      => $levels,
            'totallevels' => count($levels),
        ];

    } catch (\Throwable $e) {
        return ['has_tree' => false, 'levels' => []];
    }
}

/**
 * Builds standard template data for the sidebar navigation across all layouts.
 *
 * @param array $extras Additional context values.
 * @return array
 */
function theme_chronolms_get_sidebar_data(array $extras = []): array {
    global $CFG, $OUTPUT, $USER, $SITE, $PAGE;

    $logouturl = (new moodle_url('/login/logout.php', ['sesskey' => sesskey()]))->out(false);

    $pagepath = $PAGE->url ? $PAGE->url->get_path() : '';
    $iscodinglab_context = strpos($pagepath, '/mod/vpl') !== false || strpos($pagepath, '/mod/nextblocks') !== false || strpos($pagepath, '/theme/chronolms/layout/codinglab.php') !== false;

    $codinglab_tree = theme_chronolms_get_codinglab_tree();
    $has_codinglab_tree = !empty($codinglab_tree['has_tree']);

    $data = [
        'navitems'           => array_values(theme_chronolms_get_sidebar_nav()),
        'userfullname'       => fullname($USER),
        'usergrade'          => !empty($USER->profile['Student_Class']) ? $USER->profile['Student_Class'] : '',
        'useravatar'         => $OUTPUT->user_picture($USER, ['size' => 64, 'link' => false]),
        'userprofileurl'     => (new moodle_url('/user/profile.php', ['id' => $USER->id]))->out(false),
        'logouturl'          => $logouturl,
        'isadmin'            => is_siteadmin(),
        'adminurl'           => (new moodle_url('/admin/search.php'))->out(false),
        'sitename'           => format_string($SITE->fullname),
        'wwwroot'            => $CFG->wwwroot,
        'logourl'            => $OUTPUT->image_url('logo', 'theme_chronolms')->out(false),
        'logowhiteurl'       => $OUTPUT->image_url('logo_white', 'theme_chronolms')->out(false),
        'logomarkurl'        => $OUTPUT->image_url('logo_mark', 'theme_chronolms')->out(false),
        'has_codinglab_tree' => $has_codinglab_tree,
        'codinglab_tree'     => $codinglab_tree,
        'is_codinglab_page'  => $iscodinglab_context,
        // Remembered in a cookie (set by app.js) so the sidebar renders collapsed with no flash.
        'collapsed'          => isset($_COOKIE['clms_sidebar']) && $_COOKIE['clms_sidebar'] === 'collapsed',
    ];

    return array_merge($data, $extras);
}

/**
 * Builds standard template data for the topbar navigation across all layouts.
 *
 * @param array $extras Additional context values.
 * @return array
 */
function theme_chronolms_get_topbar_data(array $extras = []): array {
    global $CFG, $OUTPUT, $USER;

    $initials = '';
    if (!empty($USER->firstname) && !empty($USER->lastname)) {
        $initials = mb_strtoupper(mb_substr($USER->firstname, 0, 1) . mb_substr($USER->lastname, 0, 1));
    } else if (!empty($USER->username)) {
        $initials = mb_strtoupper(mb_substr($USER->username, 0, 2));
    }

    $logouturl = (new moodle_url('/login/logout.php', ['sesskey' => sesskey()]))->out(false);
    $adminurl  = (new moodle_url('/admin/search.php'))->out(false);

    // Moodle's native user menu (includes Preferences, Switch role to..., Grades, Logout)
    $usermenu = '';
    if (isloggedin() && !isguestuser()) {
        $usermenu = $OUTPUT->user_menu();
    }

    // Native Edit mode switch (renders for teachers and admins who have editing permissions)
    $editswitch = '';
    if (isloggedin() && !isguestuser() && method_exists($OUTPUT, 'edit_switch')) {
        try {
            $editswitch = $OUTPUT->edit_switch();
        } catch (\Throwable $e) {
            $editswitch = '';
        }
    }

    // Unread counts for the bell and messages icons (best effort: never break the page over a badge).
    $notifcount = 0;
    $msgcount = 0;
    if (isloggedin() && !isguestuser()) {
        try {
            if (class_exists('\\message_popup\\api')) {
                $notifcount = (int)\message_popup\api::count_unread_popup_notifications((int)$USER->id);
            }
        } catch (\Throwable $e) {
            $notifcount = 0;
        }
        try {
            $msgcount = (int)\core_message\api::count_unread_conversations($USER);
        } catch (\Throwable $e) {
            $msgcount = 0;
        }
    }
    $badge = fn(int $n): string => $n > 99 ? '99+' : (string)$n;

    $data = [
        'notifcount'      => $notifcount,
        'notifbadge'      => $notifcount ? $badge($notifcount) : '',
        'msgcount'        => $msgcount,
        'msgbadge'        => $msgcount ? $badge($msgcount) : '',
        'searchurl'       => (new moodle_url('/course/search.php'))->out(false),
        'notifurl'        => (new moodle_url('/message/output/popup/notifications.php'))->out(false),
        'messagesurl'     => (new moodle_url('/message/index.php'))->out(false),
        'calendarurl'     => (new moodle_url('/calendar/view.php'))->out(false),
        'userfullname'    => fullname($USER),
        'usergrade'       => !empty($USER->profile['Student_Class']) ? 'Grade ' . $USER->profile['Student_Class'] : '',
        'useravatar'      => $OUTPUT->user_picture($USER, ['size' => 64, 'link' => false]),
        'userprofileurl'  => (new moodle_url('/user/profile.php', ['id' => $USER->id]))->out(false),
        'userinitials'    => $initials,
        'logouturl'       => $logouturl,
        'isadmin'         => is_siteadmin(),
        'adminurl'        => $adminurl,
        'usermenu'        => $usermenu,
        'editswitch'      => $editswitch,
    ];

    return array_merge($data, $extras);
}

/**
 * Retrieves the course overview image URL if configured in Moodle, or null.
 *
 * @param object|stdClass|int $course
 * @return string|null
 */
function theme_chronolms_get_course_image($course): ?string {
    global $CFG;
    require_once($CFG->libdir . '/filelib.php');

    $courseid = is_object($course) ? (int)$course->id : (int)$course;
    if ($courseid <= 0 || $courseid === SITEID) {
        return null;
    }

    $context = context_course::instance($courseid, IGNORE_MISSING);
    if (!$context) {
        return null;
    }

    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'filename', false);
    foreach ($files as $file) {
        if ($file->is_valid_image()) {
            return moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                null,
                $file->get_filepath(),
                $file->get_filename()
            )->out(false);
        }
    }

    return null;
}

/**
 * Generates a clean 2-4 character shortcode for courses lacking an overview image.
 *
 * @param string $fullname
 * @return string
 */
function theme_chronolms_get_course_shortcode(string $fullname): string {
    $words = preg_split('/[\s\-_]+/', trim($fullname));
    if (count($words) >= 2) {
        $code = mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1);
    } else {
        $code = mb_substr($fullname, 0, 3);
    }
    return mb_strtoupper($code);
}

/**
 * Calculates standardized course progress data for a given user.
 *
 * @param object|int $course Course object or ID.
 * @param int|null $userid User ID (defaults to current user).
 * @return array ['pct' => int, 'done' => int, 'total' => int, 'status' => string, 'iscomplete' => bool, 'btnlabel' => string]
 */
function theme_chronolms_get_course_progress($course, ?int $userid = null): array {
    global $DB, $USER, $CFG;

    if ($userid === null) {
        $userid = (int)$USER->id;
    }

    $courseobj = is_object($course) ? $course : $DB->get_record('course', ['id' => (int)$course], '*', IGNORE_MISSING);
    if (!$courseobj || $courseobj->id == SITEID) {
        return [
            'pct' => 0,
            'done' => 0,
            'total' => 0,
            'status' => 'notstarted',
            'iscomplete' => false,
            'btnlabel' => 'Start',
        ];
    }

    $pct = 0;
    $done = 0;
    $total = 0;

    try {
        if (file_exists($CFG->libdir . '/completionlib.php')) {
            require_once($CFG->libdir . '/completionlib.php');
        }
        if (class_exists('completion_info')) {
            $completion = new completion_info($courseobj);
            if ($completion->is_enabled()) {
                $activities = $completion->get_activities();
                $total = count($activities);
                foreach ($activities as $a) {
                    $data = $completion->get_data($a, false, $userid);
                    if ($data && ($data->completionstate == COMPLETION_COMPLETE || $data->completionstate == COMPLETION_COMPLETE_PASS)) {
                        $done++;
                    }
                }
                $pct = $total > 0 ? (int)round(($done / $total) * 100) : 0;
            }
        }
    } catch (Throwable $e) {
        $pct = 0;
        $done = 0;
        $total = 0;
    }

    // Progress is measured against the activities that completion is tracked on: only those can ever be
    // marked done, so dividing by every activity in the course (as this used to) left courses stuck below
    // 100% and their badges locked for good. When nothing is tracked there is nothing to measure.
    if ($total == 0) {
        $pct = 0;
    }

    // Ensure non-zero progress is visible if student has completed at least one activity
    if ($done > 0 && $pct === 0) {
        $pct = 1;
    }

    if ($pct >= 100) {
        $status = 'complete';
        $btnlabel = 'View Course';
    } else if ($pct > 0) {
        $status = 'inprogress';
        $btnlabel = 'Resume';
    } else {
        $status = 'notstarted';
        $btnlabel = 'Start';
    }

    return [
        'pct' => $pct,
        'done' => $done,
        'total' => $total,
        'status' => $status,
        'iscomplete' => ($status === 'complete'),
        'btnlabel' => $btnlabel,
    ];
}




/**
 * End-of-body HTML for the theme's standalone pages (learning path, progress, assessments).
 *
 * Those pages print their own document instead of going through $OUTPUT->footer(), and the footer is
 * what normally swaps Moodle's end-of-page placeholder for the queued JavaScript. Without this the
 * page has no JavaScript at all: no app.js, no Bootstrap menus, no sidebar toggle.
 *
 * @return string
 */
function theme_chronolms_standalone_end_html(): string {
    global $OUTPUT, $PAGE;
    $html = $OUTPUT->standard_end_of_body_html();
    return preg_replace_callback('/%%ENDHTML-[A-Za-z0-9]+%%/', function () use ($PAGE) {
        return $PAGE->requires->get_end_code();
    }, $html);
}

/**
 * Header shown above Coding Lab activities (VPL and NextBlocks): where you are, which problem of
 * the set this is, and links to the previous and next problem. Presentation only; it reads the
 * course structure through Moodle's own modinfo and changes nothing.
 *
 * @return string HTML, or '' when the current page is not a coding lab activity
 */
function theme_chronolms_get_lab_header(): string {
    global $PAGE, $OUTPUT;

    // moodle_page has __get but no __isset, so ?? and empty() on its properties are always "unset".
    $cm = $PAGE->cm;
    if (!$cm || !in_array($cm->modname, ['vpl', 'nextblocks'], true) || !$PAGE->course) {
        return '';
    }
    try {
        $modinfo = get_fast_modinfo($PAGE->course);
        $siblings = [];
        foreach ($modinfo->get_instances_of($cm->modname) as $inst) {
            if ($inst->uservisible) {
                $siblings[] = $inst;
            }
        }
        $pos = 0;
        foreach ($siblings as $i => $inst) {
            if ((int) $inst->id === (int) $cm->id) {
                $pos = $i;
                break;
            }
        }
        $link = function ($inst) {
            return $inst ? [
                'name' => format_string($inst->name),
                'url' => (new moodle_url('/mod/' . $inst->modname . '/view.php', ['id' => $inst->id]))->out(false),
            ] : null;
        };
        $prev = $pos > 0 ? $link($siblings[$pos - 1]) : null;
        $next = $pos < count($siblings) - 1 ? $link($siblings[$pos + 1]) : null;
        $kind = $cm->modname === 'vpl' ? 'Text coding' : 'Block coding';

        return $OUTPUT->render_from_template('theme_chronolms/coding_lab/header', [
            'title' => format_string($cm->name),
            'coursename' => format_string($PAGE->course->fullname),
            'courseurl' => (new moodle_url('/course/view.php', ['id' => $PAGE->course->id]))->out(false),
            // NextBlocks' own list page throws an error (it raises an event without a context), so its
            // breadcrumb goes to the course page instead.
            'labsurl' => $cm->modname === 'vpl'
                ? (new moodle_url('/mod/vpl/index.php', ['id' => $PAGE->course->id]))->out(false)
                : (new moodle_url('/course/view.php', ['id' => $PAGE->course->id]))->out(false),
            'kind' => $kind,
            'hasposition' => count($siblings) > 1,
            'position' => $pos + 1,
            'total' => count($siblings),
            'prev' => $prev,
            'next' => $next,
            'iconarrow' => \theme_chronolms\icons::svg('arrow-right'),
            'iconcode' => \theme_chronolms\icons::svg($cm->modname === 'vpl' ? 'code' : 'blocks'),
        ]);
    } catch (\Throwable $e) {
        debugging('Coding lab header: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return '';
    }
}

/**
 * What changed since the student last saw their progress: points gained, a new level, newly earned badges.
 * Remembered in a user preference, so each moment is celebrated once. The first time there is nothing to
 * compare with, so it only records the starting point.
 *
 * @param int $userid student
 * @param int $points current points
 * @param array $level gamification::level() result
 * @param array|null $badges badges as shown on the dashboard (id, name, icon, desc, unlocked), or null to leave
 *                           badge state alone (pages that do not compute badges)
 * @return string a <script type="application/json"> block for javascript/app.js, or '' when there is nothing new
 */
function theme_chronolms_celebration(int $userid, int $points, array $level, ?array $badges = null): string {
    $pref = 'theme_chronolms_seen';
    $seen = json_decode((string)get_user_preferences($pref, '', $userid), true);
    $earned = [];
    if ($badges !== null) {
        foreach ($badges as $b) {
            if (!empty($b['unlocked'])) {
                $earned[$b['id']] = $b;
            }
        }
    }
    $now = [
        'points' => $points,
        'level' => (int)$level['level'],
        'badges' => $badges !== null ? array_keys($earned) : ($seen['badges'] ?? []),
    ];
    if ($seen !== $now) {
        set_user_preference($pref, json_encode($now), $userid);
    }
    if (!is_array($seen)) {
        return '';
    }

    $out = [];
    $gain = $points - (int)($seen['points'] ?? $points);
    if ($gain > 0) {
        $out['xp'] = ['gain' => $gain, 'pct' => (int)$level['pct'], 'level' => (int)$level['level']];
    }
    if ((int)$level['level'] > (int)($seen['level'] ?? $level['level'])) {
        $out['levelup'] = ['level' => (int)$level['level'], 'title' => $level['title'], 'ismax' => !empty($level['ismax'])];
    }
    if ($badges !== null) {
        foreach (array_diff(array_keys($earned), $seen['badges'] ?? []) as $id) {
            $out['badges'][] = ['name' => $earned[$id]['name'], 'icon' => $earned[$id]['icon'], 'desc' => $earned[$id]['desc']];
        }
    }
    if (!$out) {
        return '';
    }
    return '<script type="application/json" id="clms-celebrate">' . json_encode($out, JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
}
