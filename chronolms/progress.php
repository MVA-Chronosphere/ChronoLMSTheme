<?php
/**
 * ChronoLMS Student Progress & Learning Analytics Dashboard.
 *
 * @package    theme_chronolms
 * @copyright  2026 Chronosphere Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_once($CFG->dirroot . '/theme/chronolms/lib.php');

global $CFG, $OUTPUT, $PAGE, $USER, $DB, $SITE;

$courseid     = optional_param('id', 0, PARAM_INT);
$targetuserid = optional_param('userid', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/theme/chronolms/progress.php', array_filter(['id' => $courseid, 'userid' => $targetuserid])));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('base');
$PAGE->set_title(get_string('progressanalytics', 'theme_chronolms') . ' | ChronoLMS');
$PAGE->set_heading(get_string('progressanalytics', 'theme_chronolms'));
theme_chronolms_setup_page();   // loads app.js and Bootstrap (these pages render their own HTML, not a layout file)

require_once($CFG->dirroot . '/user/profile/lib.php');
if (file_exists($CFG->libdir . '/completionlib.php')) {
    require_once($CFG->libdir . '/completionlib.php');
}

$is_staff = theme_chronolms_is_staff($courseid);

$search = optional_param('search', '', PARAM_RAW_TRIMMED);

// If not staff, enforce current user
if (!$is_staff) {
    $targetuserid = $USER->id;
} else if ($search) {
    try {
        $concat = $DB->sql_concat('firstname', "' '", 'lastname');
        $sql = "SELECT id, firstname, lastname, email
                  FROM {user}
                 WHERE deleted = 0 AND suspended = 0 AND id > 1 AND (
                       " . $DB->sql_like($concat, ':q1', false) . "
                    OR " . $DB->sql_like('email', ':q2', false) . "
                 )";
        $found = $DB->get_records_sql($sql, ['q1' => '%' . $search . '%', 'q2' => '%' . $search . '%'], 0, 1);
        if (!empty($found)) {
            $first_found = reset($found);
            $targetuserid = $first_found->id;
            $targetuser = $first_found;
        }
    } catch (\Throwable $e_search) {
        // Fallback
    }
}

if (empty($targetuser)) {
    if (!$targetuserid || $targetuserid == $USER->id) {
        $targetuser = $USER;
    } else {
        $targetuser = $DB->get_record('user', ['id' => $targetuserid, 'deleted' => 0]);
        if (!$targetuser) {
            $targetuser = $USER;
        }
    }
}

profile_load_data($targetuser);

$has_analytics = file_exists($CFG->dirroot . '/local/code_analytics/index.php');
$executive_analytics_url = $has_analytics ? (new moodle_url('/local/code_analytics/index.php', $courseid > 1 ? ['courseid' => $courseid] : []))->out(false) : null;
$student_progress_url    = (new moodle_url('/theme/chronolms/progress.php', $courseid > 1 ? ['id' => $courseid] : []))->out(false);

// If staff, build student options dropdown
$student_options = [];
if ($is_staff) {
    try {
        if ($courseid > 1) {
            $context = context_course::instance($courseid, IGNORE_MISSING);
            $enrolledusers = get_enrolled_users($context, 'moodle/course:isincompletionreports', 0, 'u.id, u.firstname, u.lastname, u.email');
        } else {
            $enrolledusers = $DB->get_records('user', ['deleted' => 0, 'suspended' => 0], 'firstname ASC, lastname ASC', 'id, firstname, lastname, email', 0, 250);
        }
        if (!empty($enrolledusers)) {
            foreach ($enrolledusers as $eu) {
                if ($eu->id == 1) {
                    continue; // Skip guest
                }
                $fn = trim($eu->firstname ?? '');
                $ln = trim($eu->lastname ?? '');
                $initials = mb_strtoupper(mb_substr($fn, 0, 1) . mb_substr($ln, 0, 1));
                if (empty($initials)) {
                    $initials = 'ST';
                }
                $name = fullname($eu);
                $student_options[] = [
                    'id'       => $eu->id,
                    'name'     => $name,
                    'email'    => $eu->email ?? '',
                    'initials' => $initials,
                    'selected' => ($eu->id == $targetuser->id),
                    'url'      => (new moodle_url('/theme/chronolms/progress.php', array_filter(['id' => $courseid, 'userid' => $eu->id])))->out(false),
                ];
            }
        }
    } catch (\Throwable $e_users) {
        $student_options = [];
    }
}
$students_json = json_encode(array_values($student_options));

// Get enrolled courses for target user
if ($targetuser->id == $USER->id) {
    $enrolledcourses = enrol_get_my_courses(null, 'fullname ASC');
} else {
    require_once($CFG->dirroot . '/enrol/locallib.php');
    $enrolledcourses = enrol_get_users_courses($targetuser->id, true, 'id, fullname, shortname');
}
$total_enrolled = count($enrolledcourses);

// Compute overall completion, challenge stats, and per-course breakdown
$total_activities = 0;
$completed_activities = 0;
$total_challenges = 0;
$completed_challenges = 0;
$courses_data = [];

$grad_classes = ['blue', 'indigo', 'cyan', 'green'];
$idx = 0;

foreach ($enrolledcourses as $c) {
    $cid = $c->id;
    $c_total = 0;
    $c_complete = 0;
    $c_vpl_total = 0;
    $c_vpl_complete = 0;

    $modinfo = get_fast_modinfo($c);
    $completion = class_exists('completion_info') ? new completion_info($c) : null;
    $comp_enabled = $completion && $completion->is_enabled();

    $resume_url = new moodle_url('/course/view.php', ['id' => $cid]);
    $lab_url = null;

    foreach ($modinfo->get_cms() as $cm) {
        if (!$cm->uservisible) {
            continue;
        }
        $is_code_mod = ($cm->modname === 'vpl' || $cm->modname === 'nextblocks');
        if ($is_code_mod) {
            $c_vpl_total++;
            $total_challenges++;
            if (!$lab_url) {
                if ($cm->modname === 'nextblocks') {
                    $lab_url = new moodle_url('/mod/nextblocks/view.php', ['id' => $cm->id]);
                } else {
                    $lab_url = new moodle_url('/mod/vpl/view.php', ['id' => $cm->id]);
                }
            }
        }

        if ($comp_enabled && $cm->completion != COMPLETION_TRACKING_NONE) {
            $c_total++;
            $total_activities++;
            $cdata = $completion->get_data($cm, false, $targetuser->id);
            if ($cdata && ($cdata->completionstate == COMPLETION_COMPLETE || $cdata->completionstate == COMPLETION_COMPLETE_PASS)) {
                $c_complete++;
                $completed_activities++;
                if ($is_code_mod) {
                    $c_vpl_complete++;
                    $completed_challenges++;
                }
            }
        }
    }

    if (!$lab_url) {
        $lab_url = new moodle_url('/course/view.php', ['id' => $cid]);
    }

    $true_total_items     = max($c_total, $c_vpl_total);
    $true_completed_items = max($c_complete, $c_vpl_complete);
    $is_pure_lab_course   = ($c_vpl_total > 0 && ($c_vpl_total >= $c_total || $c_total <= 4));
    $non_code_total       = max(0, $true_total_items - $c_vpl_total);
    $non_code_done        = max(0, $true_completed_items - $c_vpl_complete);
    $has_theory           = ($non_code_total > 0 && !$is_pure_lab_course);

    // Same number as the dashboard and the Learning Path (completion on tracked activities).
    $c_pct = (int)theme_chronolms_get_course_progress($c, $targetuser->id)['pct'];

    $courses_data[] = [
        'id'                 => $cid,
        'fullname'           => html_entity_decode(format_string($c->fullname), ENT_QUOTES),
        'pct'                => $c_pct,
        'is_pure_lab_course' => $is_pure_lab_course,
        'challenges_total'   => $c_vpl_total,
        'challenges_done'    => $c_vpl_complete,
        'has_theory'         => $has_theory,
        'non_code_total'     => $non_code_total,
        'non_code_done'      => $non_code_done,
        'completed_items'    => $true_completed_items,
        'total_items'        => $true_total_items,
        'resume_url'         => $resume_url->out(false),
        'lab_url'            => $lab_url->out(false),
        'grad_class'         => $grad_classes[$idx % count($grad_classes)],
    ];
    $idx++;
}

// Overall completion: finished tracked activities over all tracked activities, across the courses.
$overall_pct = $total_activities > 0 ? (int)round(($completed_activities / $total_activities) * 100) : 0;

// Coding practice (VPL and NextBlocks), straight from submissions and the gradebook.
$coding = \theme_chronolms\progress_data::coding_stats($enrolledcourses, (int)$targetuser->id);

// Streak data
$streak = theme_chronolms_get_streak($targetuser->id);

// Badges: the same set the dashboard shows.
$badgeset = \theme_chronolms\learning_path::badges($enrolledcourses, (int)$targetuser->id, $streak, $targetuser);
$milestonebadges = $badgeset['badges'];
$unlockedcount = $badgeset['unlocked'];
$nextbadge = $badgeset['next'];

// Topic mastery: each topic (section) of the course they have got furthest in.
$palette = [
    ['color' => 'blue',   'grad' => 'linear-gradient(90deg, #1565c0, #1976d2)'],
    ['color' => 'cyan',   'grad' => 'linear-gradient(90deg, #0284c7, #06b6d4)'],
    ['color' => 'indigo', 'grad' => 'linear-gradient(90deg, #4f46e5, #6366f1)'],
    ['color' => 'green',  'grad' => 'linear-gradient(90deg, #059669, #10b981)'],
    ['color' => 'orange', 'grad' => 'linear-gradient(90deg, #d97706, #f59e0b)'],
];
$topicdata = \theme_chronolms\progress_data::topic_mastery($courses_data, $enrolledcourses, (int)$targetuser->id);
$competencies = [];
foreach ($topicdata['topics'] as $i => $t) {
    $style = $palette[$i % count($palette)];
    $competencies[] = [
        'name'       => $t['name'],
        'tag'        => $t['done'] . ' of ' . $t['total'],
        'pct'        => $t['pct'],
        'color'      => $style['color'],
        'color_grad' => $style['grad'],
    ];
}

$templatedata = [
    'userfullname'            => fullname($targetuser),
    'is_staff'                => $is_staff,
    'executive_analytics_url' => $executive_analytics_url,
    'student_progress_url'    => $student_progress_url,
    'student_options'         => $student_options,
    'has_student_options'     => !empty($student_options),
    'students_json'           => $students_json,
    'is_viewing_other'        => ($targetuser->id != $USER->id),
    'search_query_value'      => ($targetuser->id != $USER->id) ? fullname($targetuser) : '',
    'overall_completion_pct'  => $overall_pct,
    'total_courses'           => $total_enrolled,
    'challenges_done'         => $completed_challenges,
    'challenges_total'        => $coding['total'],
    'challenges_tried'        => $coding['tried'],
    'has_average'             => $coding['average'] !== null,
    'average_score'           => $coding['average'],
    'graded_text'             => $coding['graded'] . ' graded ' . ($coding['graded'] === 1 ? 'challenge' : 'challenges'),
    'overall_completed'       => $completed_activities,
    'overall_total'           => $total_activities,
    'streak_days'             => $streak['count'],
    'streak_active'           => $streak['active'],
    'courses'                 => $courses_data,
    'has_courses'             => !empty($courses_data),
    'milestonebadges'         => $milestonebadges,
    'unlockedcount'           => $unlockedcount,
    'totalbadgescount'        => count($milestonebadges),
    'nextbadge'               => $nextbadge,
    'competencies'            => $competencies,
    'has_topics'              => !empty($competencies),
    'topic_course'            => $topicdata['course'],
    'learningpath_url'        => (new moodle_url('/theme/chronolms/learningpath.php'))->out(false),
];

$renderedsidebar = $OUTPUT->render_from_template('theme_chronolms/nav/sidebar', theme_chronolms_get_sidebar_data());
$topbardata      = theme_chronolms_get_topbar_data();

echo $OUTPUT->doctype();
?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <title><?php echo get_string('progressanalytics', 'theme_chronolms'); ?> | ChronoLMS</title>
    <link rel="shortcut icon" href="<?php echo $OUTPUT->favicon(); ?>" />
    <?php echo $OUTPUT->standard_head_html(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body <?php echo $OUTPUT->body_attributes(['chronolms-authenticated', 'chronolms-progress-page-body']); ?>>
<?php echo $OUTPUT->standard_top_of_body_html(); ?>

<div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>
<div class="chronolms-app">
    <?php echo $renderedsidebar; ?>

    <div class="chronolms-content-wrapper" id="chronolms-content-wrapper">
        <?php echo $OUTPUT->render_from_template('theme_chronolms/nav/topbar', $topbardata); ?>

        <main class="chronolms-main chronolms-progress" id="page-content" role="main">
            <?php echo $OUTPUT->render_from_template('theme_chronolms/progress/page', $templatedata); ?>
        </main>
    </div>
</div>

<?php echo theme_chronolms_standalone_end_html(); ?>
</body>
</html>
