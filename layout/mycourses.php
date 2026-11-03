<?php
/**
 * ChronoLMS My Courses page layout.
 *
 * @package    theme_chronolms
 * @copyright  2026 Chronosphere Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
global $OUTPUT, $USER, $PAGE, $CFG, $SITE;

$maincontent = $OUTPUT->main_content();
theme_chronolms_setup_page();
$pluginblocks = theme_chronolms_render_plugin_blocks();

require_once($CFG->dirroot . '/user/profile/lib.php');
if (file_exists($CFG->libdir . '/completionlib.php')) {
    require_once($CFG->libdir . '/completionlib.php');
}
profile_load_data($USER);

$renderedsidebar = $OUTPUT->render_from_template('theme_chronolms/nav/sidebar', theme_chronolms_get_sidebar_data());

$topbardata = theme_chronolms_get_topbar_data();

// Build courses data for the grid: display all available courses on the platform.
// Tagged courses (where the user is enrolled or has access) show active progress and entry buttons.
// Untagged courses show a clear 'Not Enrolled / Locked' state with proper flow of control.
global $DB;
$site_courses = $DB->get_records_select('course', 'id > 1 AND visible = 1', null, 'sortorder ASC');
$enrolled_courses = enrol_get_my_courses(null, 'fullname ASC');
$enrolled_ids = array_map(function($c) { return (int)$c->id; }, $enrolled_courses);
$is_admin = is_siteadmin();

$coursegrid = [];
foreach ($site_courses as $c) {
    if ($c->id == SITEID) continue;

    $is_tagged = $is_admin || in_array((int)$c->id, $enrolled_ids);
    $courseimage = theme_chronolms_get_course_image($c);
    $coursecode  = theme_chronolms_get_course_shortcode($c->fullname);

    if ($is_tagged) {
        $prog = theme_chronolms_get_course_progress($c, $USER->id);
        $coursegrid[] = [
            'band'        => ['violet', 'green', 'blue'][count($coursegrid) % 3],
            'id'          => $c->id,
            'fullname'    => format_string($c->fullname),
            'courseimage' => $courseimage,
            'coursecode'  => $coursecode,
            'pct'         => $prog['pct'],
            'done'        => $prog['done'],
            'total'       => $prog['total'],
            'grade'       => !empty($USER->profile['Student_Class']) ? 'Grade ' . $USER->profile['Student_Class'] : '',
            'status'      => $prog['status'],
            'iscomplete'  => $prog['iscomplete'],
            'btnlabel'    => $prog['btnlabel'],
            'btnclass'    => 'btn-resume' . ($prog['pct'] > 0 || $prog['iscomplete'] ? '' : ' btn-start'),
            'url'         => (new moodle_url('/course/view.php', ['id' => $c->id]))->out(false),
            'isenrolled'  => true,
            'taglabel'    => 'Enrolled',
        ];
    } else {
        $coursegrid[] = [
            'band'        => 'grey',
            'id'          => $c->id,
            'fullname'    => format_string($c->fullname),
            'courseimage' => $courseimage,
            'coursecode'  => $coursecode,
            'pct'         => 0,
            'done'        => 0,
            'total'       => 0,
            'grade'       => !empty($USER->profile['Student_Class']) ? 'Grade ' . $USER->profile['Student_Class'] : '',
            'status'      => 'locked',
            'iscomplete'  => false,
            'btnlabel'    => 'Not Enrolled',
            'btnclass'    => 'btn-resume btn-locked',
            'url'         => '#',
            'isenrolled'  => false,
            'taglabel'    => 'Not Enrolled',
        ];
    }
}

$mine = array_values(array_filter($coursegrid, fn($c) => $c['isenrolled']));
$others = array_values(array_filter($coursegrid, fn($c) => !$c['isenrolled']));
$counts = ['all' => count($mine), 'inprogress' => 0, 'notstarted' => 0, 'complete' => 0];
foreach ($mine as $c) {
    if (isset($counts[$c['status']])) {
        $counts[$c['status']]++;
    }
}

echo $OUTPUT->doctype();
?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <title><?php echo get_string('mycourses'); ?> | ChronoLMS</title>
    <link rel="shortcut icon" href="<?php echo $OUTPUT->favicon(); ?>" />
    <?php echo $OUTPUT->standard_head_html(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body <?php echo $OUTPUT->body_attributes(['chronolms-authenticated']); ?>>
<?php echo $OUTPUT->standard_top_of_body_html(); ?>

<div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>
<div class="chronolms-app" id="page">
    <?php echo $renderedsidebar; ?>
    <div class="chronolms-content-wrapper" id="chronolms-content-wrapper">
        <?php echo $OUTPUT->render_from_template('theme_chronolms/nav/topbar', $topbardata); ?>
        <main class="chronolms-main chronolms-mycourses" id="page-content" role="main">
            <section id="region-main" aria-label="<?php echo get_string('content'); ?>">
            <?php echo $OUTPUT->render_from_template('theme_chronolms/courses/page', [
                'courses'      => $mine,
                'hascourses'   => count($mine) > 0,
                'othercourses' => $others,
                'hasothers'    => count($others) > 0,
                'counts'       => $counts,
                'dashboardurl' => (new moodle_url('/my/'))->out(false),
            ]); ?>
            <div class="d-none">
                <?php echo $maincontent; ?>
            </div>
            </section>
        </main>
    </div>
</div>

<div class="chronolms-plugin-blocks" hidden><?php echo $pluginblocks; ?></div>

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

<?php echo $OUTPUT->standard_end_of_body_html(); ?>
</body>
</html>
