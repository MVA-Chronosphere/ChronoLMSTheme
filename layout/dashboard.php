<?php
/**
 * ChronoLMS Student Dashboard layout.
 *
 * @package    theme_chronolms
 * @copyright  2026 Chronosphere Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
global $OUTPUT, $USER, $CFG, $PAGE, $SITE;

$maincontent = $OUTPUT->main_content();
theme_chronolms_setup_page();
$pluginblocks = theme_chronolms_render_plugin_blocks();

// Load profile fields
require_once($CFG->dirroot . '/user/profile/lib.php');
// completionlib.php defines completion_info; load it explicitly then verify.
if (file_exists($CFG->libdir . '/completionlib.php')) {
    require_once($CFG->libdir . '/completionlib.php');
}
profile_load_data($USER);

// Streak data (graceful fallback)
$streak = theme_chronolms_get_streak($USER->id);

// All enrolled courses for Overall Progress calculation
$allenrolledcourses = enrol_get_my_courses(null, 'fullname ASC');
$totalcourses = 0;
$inprogresscount = 0;
$completedcount = 0;
$sumpct = 0;

foreach ($allenrolledcourses as $c) {
    if ($c->id == SITEID) continue;
    $totalcourses++;
    $prog = theme_chronolms_get_course_progress($c, $USER->id);
    $sumpct += $prog['pct'];
    if ($prog['status'] === 'complete') {
        $completedcount++;
    } else if ($prog['status'] === 'inprogress') {
        $inprogresscount++;
    }
}

$overallpct = $totalcourses > 0 ? (int)round($sumpct / $totalcourses) : 0;
$circumference = 2 * M_PI * 37; // ~232.47
$ringoffset = $circumference - ($overallpct / 100.0) * $circumference;

// Gather recent courses (Continue Learning)
$recentcourses = array_slice(enrol_get_my_courses(null, 'ul.timeaccess DESC'), 0, 4);
$coursecards   = [];

foreach ($recentcourses as $c) {
    if ($c->id == SITEID) continue;
    $prog        = theme_chronolms_get_course_progress($c, $USER->id);
    $courseimage = theme_chronolms_get_course_image($c);
    $coursecode  = theme_chronolms_get_course_shortcode($c->fullname);

    $coursecards[] = [
        'band'        => ['violet', 'green', 'blue'][count($coursecards) % 3],
        'id'          => $c->id,
        'fullname'    => format_string($c->fullname),
        'courseimage' => $courseimage,
        'coursecode'  => $coursecode,
        'pct'         => $prog['pct'],
        'status'      => $prog['status'],
        'iscomplete'  => $prog['iscomplete'],
        'btnlabel'    => $prog['btnlabel'],
        'btnclass'    => ($prog['pct'] > 0) ? 'btn-primary' : 'btn-outline-primary',
        'url'         => (new moodle_url('/course/view.php', ['id' => $c->id]))->out(false),
    ];
}

// Calendar events (Today's Tasks)
$now       = time();
$events    = \core_calendar\local\api::get_events($now, $now + (7 * 86400), null, null, null, true, true);
$taskitems = [];
foreach ($events as $e) {
    $taskitems[] = [
        'name'      => format_string($e->get_name()),
        'coursename'=> ($e->get_course() && $e->get_course()->get('id') > 0) ? format_string($e->get_course()->get('fullname')) : '',
        'timeformat'=> userdate($e->get_times()->get_start_time()->getTimestamp(), get_string('strftimetime', 'langconfig')),
    ];
}

// Badges: shared with the Progress page (\theme_chronolms\learning_path::badges).
$badgeset = \theme_chronolms\learning_path::badges($allenrolledcourses, (int)$USER->id, $streak);
$milestonebadges = $badgeset['badges'];
$unlockedcount = $badgeset['unlocked'];
$nextbadge = $badgeset['next'];

// Engagement: points, level, week strip, class leaderboard/goal, "up next".
$game = new \theme_chronolms\gamification((int)$USER->id);
$mypoints = $game->points_for([(int)$USER->id])[(int)$USER->id] ?? 0;
$leveldata = $game->level($mypoints);
$weekstrip = $game->week_strip();
$classdata = $game->class_data();
// Practice shortcuts: the student's own next coding and block-coding activities (none shown if they have none).
$recentids = array_map(function ($c) {
    return $c->id;
}, $recentcourses);
$practicecourses = array_merge($recentcourses, array_filter($allenrolledcourses, function ($c) use ($recentids) {
    return !in_array($c->id, $recentids);
}));
$practicecode   = $game->practice_link($practicecourses, 'vpl');
$practiceblocks = $game->practice_link($practicecourses, 'nextblocks');

// "Up next": prefer a course already under way (most recently used first), else any with work left.
$upnext = null;
foreach ([true, false] as $startedonly) {
    foreach ($recentcourses as $c) {
        if ($c->id == SITEID) {
            continue;
        }
        $prog = theme_chronolms_get_course_progress($c, $USER->id);
        if ($prog['pct'] >= 100 || ($startedonly && $prog['pct'] <= 0)) {
            continue;
        }
        $step = $game->next_activity($c);
        if (!$step) {
            continue;
        }
        $upnext = [
            'coursename' => format_string($c->fullname),
            'pct'        => $prog['pct'],
            'nextname'   => $step['name'],
            'remaining'  => $step['remaining'],
            'remainingtext' => $step['remaining'] . ($step['remaining'] === 1 ? ' activity' : ' activities') . ' left',
            'url'        => $step['url'],
            'btnlabel'   => $prog['pct'] > 0 ? 'Continue' : 'Start',
        ];
        break 2;
    }
}

$hourofday = (int)userdate(time(), '%H');
$greeting = $hourofday < 12 ? 'Good morning' : ($hourofday < 17 ? 'Good afternoon' : 'Good evening');

$templatedata = [
    'greeting'          => $greeting,
    'upnext'            => $upnext,
    'hasupnext'         => $upnext !== null,
    'points'            => $mypoints,
    'pointsfmt'         => number_format($mypoints),
    'level'             => $leveldata,
    'weekdays'          => $weekstrip['days'],
    'donetoday'         => $weekstrip['doneToday'],
    'class'             => $classdata,
    'practicecode'      => $practicecode,
    'practiceblocks'    => $practiceblocks,
    'haspractice'       => $practicecode !== null || $practiceblocks !== null,
    'hasclass'          => $classdata !== null,
    'userinitials'      => \core_text::strtoupper(\core_text::substr($USER->firstname, 0, 1) . \core_text::substr($USER->lastname, 0, 1)),
    'userfullshort'     => $USER->firstname . ' ' . \core_text::strtoupper(\core_text::substr($USER->lastname, 0, 1)) . '.',
    'userfirstname'     => $USER->firstname,
    'usergrade'         => !empty($USER->profile['Student_Class']) ? 'Grade ' . $USER->profile['Student_Class'] : '',
    'streak'            => $streak['count'],
    'streakone'         => (int)$streak['count'] === 1,
    'streakactive'      => !empty($streak['active']),
    'coursecards'       => $coursecards,
    'hascourses'        => count($coursecards) > 0,
    'taskitems'         => $taskitems,
    'hastasks'          => count($taskitems) > 0,
    'mycoursesurl'      => (new moodle_url('/my/courses.php'))->out(false),
    'calendarurl'       => (new moodle_url('/calendar/view.php'))->out(false),
    'progressurl'       => (new moodle_url('/grade/report/overview/index.php'))->out(false),
    'quote'             => '"Small steps every day lead to big results."',
    'wwwroot'           => $CFG->wwwroot,
    // Real server-calculated overall progress stats
    'overallpct'        => $overallpct,
    'overallringoffset' => round($ringoffset, 1),
    'coursesinprogress' => $inprogresscount,
    'coursescompleted'  => $completedcount,
    'totalcourses'      => $totalcourses,
    // Milestone badges for achievements card
    'milestonebadges'   => $milestonebadges,
    'unlockedcount'     => $unlockedcount,
    'totalbadgescount'  => count($milestonebadges),
    'nextbadge'         => $nextbadge,
];

// Inject sidebar + topbar and render dashboard template
$renderedsidebar = $OUTPUT->render_from_template('theme_chronolms/nav/sidebar', theme_chronolms_get_sidebar_data());

$topbardata = theme_chronolms_get_topbar_data();

echo $OUTPUT->doctype();
?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <title><?php echo $OUTPUT->page_title(); ?></title>
    <link rel="shortcut icon" href="<?php echo $OUTPUT->favicon(); ?>" />
    <?php echo $OUTPUT->standard_head_html(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body <?php echo $OUTPUT->body_attributes(['chronolms-authenticated', 'chronolms-dashboard']); ?>>
<?php echo $OUTPUT->standard_top_of_body_html(); ?>

<div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>

<div class="chronolms-app" id="page">
    <?php echo $renderedsidebar; ?>

    <div class="chronolms-content-wrapper" id="chronolms-content-wrapper">
        <?php echo $OUTPUT->render_from_template('theme_chronolms/nav/topbar', $topbardata); ?>

        <main class="chronolms-main chronolms-dashboard" id="page-content" role="main">
            <section id="region-main" aria-label="<?php echo get_string('content'); ?>">
            <?php echo $OUTPUT->render_from_template('theme_chronolms/dashboard/page', $templatedata); ?>
            <div class="d-none">
                <?php echo $maincontent; ?>
            </div>
            </section>
        </main>
    </div>
</div>

<div class="chronolms-plugin-blocks" hidden><?php echo $pluginblocks; ?></div>

<!-- Floating chatbot -->
<button id="chronolms-chatbot-toggle" aria-label="Open AI assistant">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    <span class="chat-badge" id="chat-badge"></span>
</button>
<div id="chronolms-chatbot-panel" role="dialog" aria-label="AI Assistant">
    <div class="chatbot-header">
        <div class="bot-avatar"><?php echo \theme_chronolms\icons::svg('bot'); ?></div>
        <div class="bot-info"><div class="bot-name">ChronoBot</div><div class="bot-status">Powered by AI</div></div>
        <button class="chatbot-close" id="chatbot-close-btn" aria-label="Close chatbot">
            <svg width="18" height="18" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <div class="chatbot-body"><div id="chronolms-chatbot-inner"></div></div>
</div>

<?php echo theme_chronolms_celebration((int)$USER->id, (int)$mypoints, $leveldata, $milestonebadges); ?>
<?php echo $OUTPUT->standard_end_of_body_html(); ?>
</body>
</html>
