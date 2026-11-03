<?php
/**
 * ChronoLMS Multi-Track Learning Path Roadmap.
 *
 * Tracks:
 *   - Block Coding (Grades 6–8 Foundational Logic)
 *   - AI & Data Science (Grade 9+ Specialization)
 *   - Robotics & IoT (Grade 9+ Specialization)
 *   - Web & Full Stack Development (Grade 9+ Specialization)
 *   - Cybersecurity (Grade 9+ Specialization)
 *
 * @package    theme_chronolms
 * @copyright  2026 Chronosphere Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_once($CFG->dirroot . '/theme/chronolms/lib.php');

global $CFG, $OUTPUT, $PAGE, $USER, $DB, $SITE;

$PAGE->set_url(new moodle_url('/theme/chronolms/learningpath.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('base');
$PAGE->set_title(get_string('learningpath', 'theme_chronolms') . ' | ChronoLMS');
$PAGE->set_heading(get_string('learningpath', 'theme_chronolms'));
theme_chronolms_setup_page();   // loads app.js and Bootstrap (these pages render their own HTML, not a layout file)

require_once($CFG->dirroot . '/user/profile/lib.php');
if (file_exists($CFG->libdir . '/completionlib.php')) {
    require_once($CFG->libdir . '/completionlib.php');
}
profile_load_data($USER);

$renderedsidebar = $OUTPUT->render_from_template('theme_chronolms/nav/sidebar', theme_chronolms_get_sidebar_data());
$topbardata      = theme_chronolms_get_topbar_data();

// The student's courses, the tracks, and how far they have got in each stage (all from \theme_chronolms\learning_path).
$allcourses = enrol_get_my_courses(null, 'fullname ASC');
$alltracks = \theme_chronolms\learning_path::tracks();
$selected_track_id = \theme_chronolms\learning_path::detect_track($allcourses, optional_param('track', '', PARAM_ALPHANUMEXT));
$activetrack = $alltracks[$selected_track_id];

$tracktabs = [];
foreach ($alltracks as $tid => $tinfo) {
    $tracktabs[] = [
        'id'          => $tid,
        'title'       => $tinfo['title'],
        'shorttitle'  => ['block' => 'Block Coding', 'ai' => 'AI & Data', 'robotics' => 'Robotics & IoT', 'web' => 'Web Dev', 'cyber' => 'Cybersecurity'][$tid] ?? $tinfo['title'],
        'target_grade' => $tinfo['target_grade'],
        'grade_tag'   => $tinfo['grade_tag'],
        'icon'        => $tinfo['icon'],
        'url'         => (new moodle_url('/theme/chronolms/learningpath.php', ['track' => $tid]))->out(false),
        'is_active'   => ($tid === $selected_track_id),
    ];
}

$path = \theme_chronolms\learning_path::stages($selected_track_id, $allcourses, (int)$USER->id);
$stages = [];
$activitiesleft = 0;
$activitiestotal = 0;
foreach ($path['stages'] as $st) {
    $skillchips = [];
    foreach ($st['skills'] as $skill) {
        $skillchips[] = ['skill' => $skill];
    }
    $activitiestotal += $st['total'];
    $activitiesleft += max(0, $st['total'] - $st['done']);

    if ($st['status'] === 'completed') {
        $btnlabel = 'Review stage';
    } else if ($st['status'] === 'in_progress') {
        $btnlabel = ($st['pct'] > 0) ? 'Resume stage' : 'Start stage';
    } else {
        $btnlabel = 'Preview';
    }
    if (!$st['enrolled']) {
        $activities = $st['courseurl'] !== '' ? 'Not enrolled yet' : 'Course not available';
    } else if ($st['total'] > 0) {
        $activities = $st['done'] . ' of ' . $st['total'] . ' activities done';
    } else {
        $activities = 'No activities yet';
    }

    $stages[] = [
        'stagenum'       => $st['num'],
        'stagetag'       => trim(explode('•', $st['tag'])[1] ?? $st['tag']),
        'title'          => $st['title'],
        'subtitle'       => $st['subtitle'],
        'skillchips'     => $skillchips,
        'tool_badge'     => $st['tool_badge'],
        'tool_icon'      => $st['tool_icon'],
        'activities'     => $activities,
        'badge_name'     => $st['badge_name'],
        'badge_icon'     => $st['badge_icon'],
        'badge_earned'   => ($st['status'] === 'completed'),
        'courseid'       => $st['courseid'],
        'courseurl'      => $st['courseurl'],
        'hascourse'      => $st['courseurl'] !== '',
        'pct'            => $st['pct'],
        'status'         => $st['status'],
        'is_completed'   => ($st['status'] === 'completed'),
        'is_in_progress' => ($st['status'] === 'in_progress'),
        'is_upcoming'    => ($st['status'] === 'upcoming'),
        'btnlabel'       => $btnlabel,
    ];
}

$completedcount = $path['completed'];
$currentfocusstagenum = $path['current'];
$stagedefs = $activetrack['stages'];

$templatedata = [
    'userfirstname'        => $USER->firstname,
    'tracktabs'            => $tracktabs,
    'activetrack'          => $activetrack,
    'tracktitle'           => $activetrack['title'],
    'tracktargetgrade'     => $activetrack['target_grade'],
    'trackdesc'            => $activetrack['desc'],
    'trackbadge'           => $activetrack['badge'],
    'overalltrackpct'      => $path['pct'],
    'completedcount'       => $completedcount,
    'totalstages'          => count($stagedefs),
    'currentfocusstagenum' => $currentfocusstagenum,
    'stages'               => $stages,
    'hasstages'            => count($stages) > 0,
    'badges'               => array_map(function ($st) {
        return ['name' => $st['badge_name'], 'icon' => $st['badge_icon'], 'earned' => $st['badge_earned'],
                'stagenum' => $st['stagenum']];
    }, $stages),
    'badgesearned'         => $completedcount,
    'activitiesleft'       => $activitiesleft,
    'activitiestotal'      => $activitiestotal,
    'upnext'               => ($completedcount < count($stages)) ? (array_values(array_filter($stages, function ($st) {
        return $st['is_in_progress'];
    }))[0] ?? null) : null,
    'trackdone'            => (count($stages) > 0 && $completedcount === count($stages)),
    'iconarrow'            => \theme_chronolms\icons::svg('arrow-right'),
    'iconcheck'            => \theme_chronolms\icons::svg('check'),
    'iconlock'             => \theme_chronolms\icons::svg('lock'),
    'iconclock'            => \theme_chronolms\icons::svg('clock'),
    'iconflag'             => \theme_chronolms\icons::svg('flag'),
    'mycoursesurl'         => (new moodle_url('/my/courses.php'))->out(false),
    'dashboardurl'         => (new moodle_url('/my/'))->out(false),
    'wwwroot'              => $CFG->wwwroot,
];

echo $OUTPUT->doctype();
?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <title><?php echo format_string($activetrack['title']); ?> | Learning Path | ChronoLMS</title>
    <link rel="shortcut icon" href="<?php echo $OUTPUT->favicon(); ?>" />
    <?php echo $OUTPUT->standard_head_html(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body <?php echo $OUTPUT->body_attributes(['chronolms-authenticated', 'chronolms-learningpath']); ?>>
<?php echo $OUTPUT->standard_top_of_body_html(); ?>

<div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>
<div class="chronolms-app" id="page">
    <?php echo $renderedsidebar; ?>

    <div class="chronolms-content-wrapper" id="chronolms-content-wrapper">
        <?php echo $OUTPUT->render_from_template('theme_chronolms/nav/topbar', $topbardata); ?>

        <main class="chronolms-main chronolms-learningpath-page" id="page-content" role="main">
            <section id="region-main" aria-label="<?php echo get_string('content'); ?>">
            <?php echo $OUTPUT->render_from_template('theme_chronolms/learningpath/page', $templatedata); ?>
            </section>
        </main>
    </div>
</div>

<button id="chronolms-chatbot-toggle" aria-label="Open AI assistant">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
</button>
<div id="chronolms-chatbot-panel" role="dialog">
    <div class="chatbot-header">
        <div class="bot-avatar"><?php echo \theme_chronolms\icons::svg('bot'); ?></div>
        <div class="bot-info"><div class="bot-name">ChronoBot</div><div class="bot-status">Powered by AI</div></div>
        <button class="chatbot-close" id="chatbot-close-btn" aria-label="Close chatbot"><svg width="18" height="18" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="chatbot-body"><div id="chronolms-chatbot-inner"></div></div>
</div>

<?php echo theme_chronolms_standalone_end_html(); ?>
</body>
</html>
