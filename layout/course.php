<?php
/**
 * ChronoLMS Course Learning page layout.
 *
 * @package    theme_chronolms
 * @copyright  2026 Chronosphere Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
global $OUTPUT, $USER, $PAGE, $CFG, $SITE, $course;

$maincontent = $OUTPUT->main_content();
theme_chronolms_setup_page();
$pluginblocks = theme_chronolms_render_plugin_blocks();

require_once($CFG->dirroot . '/user/profile/lib.php');
if (file_exists($CFG->libdir . '/completionlib.php')) {
    require_once($CFG->libdir . '/completionlib.php');
}
profile_load_data($USER);

$pagepath = $PAGE->url->get_path();
$iscodinglab = strpos($pagepath, '/mod/vpl') !== false || strpos($pagepath, '/mod/nextblocks') !== false;

$renderedsidebar = $OUTPUT->render_from_template('theme_chronolms/nav/sidebar', theme_chronolms_get_sidebar_data());
$topbardata = theme_chronolms_get_topbar_data();

// Course completion progress
$pct = 0;
if (!empty($course)) {
    $prog = theme_chronolms_get_course_progress($course, $USER->id);
    $pct  = $prog['pct'];
}

// Hero on the course home (not on activity pages): progress and one "continue" action, for enrolled students.
$coursehero = null;
if (!empty($course) && $course->id > SITEID && strpos($PAGE->pagetype, 'course-view') === 0) {
    $herocontext = context_course::instance($course->id);
    if (is_enrolled($herocontext, $USER->id, '', true) && !has_capability('moodle/course:update', $herocontext)) {
        $herogame = new \theme_chronolms\gamification((int)$USER->id);
        $herostep = $herogame->next_activity($course);
        $heropoints = $herogame->points_for([(int)$USER->id])[(int)$USER->id] ?? 0;
        $herocelebrate = theme_chronolms_celebration((int)$USER->id, (int)$heropoints, $herogame->level((int)$heropoints));
        $coursehero = [
            'title'     => format_string($course->fullname),
            'pct'       => $pct,
            'done'      => $prog['done'] ?? 0,
            'total'     => $prog['total'] ?? 0,
            'iscomplete' => $pct >= 100,
            'next'      => $herostep,
        ];
    }
}

echo $OUTPUT->doctype();
?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <title><?php echo $OUTPUT->page_title(); ?> | ChronoLMS</title>
    <link rel="shortcut icon" href="<?php echo $OUTPUT->favicon(); ?>" />
    <?php echo $OUTPUT->standard_head_html(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body <?php echo $OUTPUT->body_attributes(array_filter([
    'chronolms-authenticated',
    'chronolms-course',
    $iscodinglab ? 'chronolms-codinglab' : null,
])); ?>>
<?php echo $OUTPUT->standard_top_of_body_html(); ?>

<div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>
<div class="chronolms-app" id="page">
    <?php echo $renderedsidebar; ?>
    <div class="chronolms-content-wrapper" id="chronolms-content-wrapper">
        <?php echo $OUTPUT->render_from_template('theme_chronolms/nav/topbar', $topbardata); ?>
        <main class="chronolms-main chronolms-course<?php echo $iscodinglab ? ' chronolms-codinglab' : ''; ?>" id="page-content" role="main">
            <section id="region-main" aria-label="<?php echo get_string('content'); ?>">
            <!-- Course Header Bar (Breadcrumbs + Modern Progress Pill) -->
            <div class="course-header-bar">
                <nav class="course-breadcrumb" aria-label="Breadcrumb">
                    <a href="<?php echo new moodle_url('/my/courses.php'); ?>" class="breadcrumb-link"><?php echo get_string('mycourses'); ?></a>
                    <span class="separator" aria-hidden="true">&rsaquo;</span>
                    <?php if (!empty($course)): ?>
                        <span class="breadcrumb-current"><?php echo format_string($course->fullname); ?></span>
                    <?php endif; ?>
                </nav>
                <?php if (!empty($course) && !$coursehero): ?>
                <div class="course-progress-badge" title="<?php echo $pct; ?>% Completed">
                    <div class="progress-meta">
                        <span class="progress-label-text"><?php echo get_string('complete', 'theme_chronolms'); ?></span>
                        <span class="progress-pct-value"><?php echo $pct; ?>%</span>
                    </div>
                    <div class="progress-track">
                        <div class="progress-fill" style="width: <?php echo $pct; ?>%;"></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($coursehero): ?>
            <section class="dash-card dash-hero course-hero" aria-label="Your progress in this course">
                <div class="dash-hero-greet">Course</div>
                <h1 class="dash-hero-title"><?php echo $coursehero['title']; ?></h1>
                <div class="dash-hero-next">
                    <?php echo \theme_chronolms\icons::svg('book-open'); ?>
                    <span><?php
                    if ($coursehero['iscomplete']) {
                        echo 'You have finished every activity. Well done.';
                    } else if ($coursehero['next']) {
                        echo 'Next: ' . s($coursehero['next']['name']) . ' · ' . (int)$coursehero['next']['remaining']
                            . ($coursehero['next']['remaining'] === 1 ? ' activity' : ' activities') . ' left';
                    } else {
                        echo (int)$coursehero['done'] . ' of ' . (int)$coursehero['total'] . ' activities done';
                    }
                    ?></span>
                </div>
                <div class="dash-hero-row">
                    <div class="dash-bar" role="progressbar" aria-valuenow="<?php echo (int)$coursehero['pct']; ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Course progress"><i style="width:<?php echo (int)$coursehero['pct']; ?>%"></i></div>
                    <span class="dash-hero-pct"><?php echo (int)$coursehero['pct']; ?>%</span>
                    <?php if ($coursehero['next'] && !$coursehero['iscomplete']): ?>
                    <a class="dash-btn dash-btn-white" href="<?php echo s($coursehero['next']['url']); ?>"><?php echo $coursehero['pct'] > 0 ? 'Continue' : 'Start'; ?> <?php echo \theme_chronolms\icons::svg('arrow-right'); ?></a>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>
            <?php
            echo theme_chronolms_get_lab_header();
            echo theme_chronolms_get_secondary_navigation();
            ?>
            <?php
            // Flow of control: Ensure users can only complete activities/view resources in their tagged (enrolled) courses.
            $can_access_course = true;
            if (!empty($course) && $course->id > 1) {
                $coursecontext = context_course::instance($course->id);
                $can_access_course = is_siteadmin() || is_enrolled($coursecontext, $USER->id, '', true);
            }
            ?>
            <?php if (!$can_access_course): ?>
                <div class="chronolms-restricted-banner">
                    <div class="restricted-card">
                        <div class="restricted-icon-wrap"><?php echo \theme_chronolms\icons::svg('lock'); ?></div>
                        <h2 class="restricted-title">Course Access Restricted</h2>
                        <p class="restricted-desc">
                            You are not currently enrolled in <strong><?php echo format_string($course->fullname); ?></strong>.<br>
                            You can only complete activities and view learning resources for courses tagged to your profile.
                        </p>
                        <div class="restricted-actions">
                            <a href="<?php echo new moodle_url('/my/courses.php'); ?>" class="btn-primary">
                                &larr; Return to My Courses
                            </a>
                        </div>
                    </div>
                </div>
                <div class="d-none">
                    <?php echo $maincontent; ?>
                </div>
            <?php else: ?>
                <!-- Main Moodle course content renders here (topics format, etc.) -->
                <?php
                $actheader = $PAGE->activityheader;
                $headercontent = $actheader ? $actheader->export_for_template($OUTPUT) : null;
                if (!empty($headercontent)) {
                    echo $OUTPUT->render_from_template('core/activity_header', $headercontent);
                }
                echo $maincontent;
                ?>
            <?php endif; ?>
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

<?php if (strpos($pagepath, '/mod/nextblocks') !== false): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var outDiv = document.getElementById('output-div');
    if (outDiv) {
        var fixOutputColors = function() {
            var items = outDiv.querySelectorAll('div[style*="background-color"]');
            items.forEach(function(el) {
                el.style.setProperty('background-color', '#0f172a', 'important');
            });
        };
        var obs = new MutationObserver(fixOutputColors);
        obs.observe(outDiv, { childList: true, subtree: true });
        fixOutputColors();
    }
});
</script>
<?php endif; ?>
<?php echo $herocelebrate ?? ''; ?>
<?php echo $OUTPUT->standard_end_of_body_html(); ?>
</body>
</html>
