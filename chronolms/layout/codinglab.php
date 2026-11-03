<?php
/**
 * ChronoLMS Coding Lab layout.
 * Wraps both VPL (Virtual Programming Lab) and NextBlocks activities
 * in the custom split-pane Coding Lab UI.
 *
 * Active on:
 *   - /mod/vpl/view.php?id=X  (text-based coding — Python, JS, etc.)
 *   - /mod/nextblocks/view.php?id=X  (block-based coding)
 *
 * @package    theme_chronolms
 * @copyright  2026 Chronosphere Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
global $OUTPUT, $USER, $PAGE, $CFG, $SITE, $cm, $course;

$maincontent = $OUTPUT->main_content();
theme_chronolms_setup_page();
$pluginblocks = theme_chronolms_render_plugin_blocks();

require_once($CFG->dirroot . '/user/profile/lib.php');
profile_load_data($USER);

// Detect which coding plugin is active
$isvpl        = (strpos($PAGE->url->get_path(), '/mod/vpl') !== false);
$isnextblocks = (strpos($PAGE->url->get_path(), '/mod/nextblocks') !== false);

// Determine lab type label
$labtype = $isvpl ? 'Python / Text Coding' : ($isnextblocks ? 'Block Coding' : 'Coding Lab');

$renderedsidebar = $OUTPUT->render_from_template('theme_chronolms/nav/sidebar', theme_chronolms_get_sidebar_data());
$topbardata = theme_chronolms_get_topbar_data();

echo $OUTPUT->doctype();
?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <title><?php echo $OUTPUT->page_title(); ?> | Coding Lab | ChronoLMS</title>
    <link rel="shortcut icon" href="<?php echo $OUTPUT->favicon(); ?>" />
    <?php echo $OUTPUT->standard_head_html(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body <?php echo $OUTPUT->body_attributes(['chronolms-authenticated', 'chronolms-codinglab']); ?>>
<?php echo $OUTPUT->standard_top_of_body_html(); ?>

<div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>

<div class="chronolms-app" id="page">
    <?php echo $renderedsidebar; ?>

    <div class="chronolms-content-wrapper" id="chronolms-content-wrapper">
        <?php echo $OUTPUT->render_from_template('theme_chronolms/nav/topbar', $topbardata); ?>

        <!-- Coding Lab main area — full height, no padding -->
        <main class="chronolms-codinglab" id="page-content" role="main">
            <section id="region-main" aria-label="<?php echo get_string('content'); ?>">

            <!-- Lab header bar -->
            <div class="lab-header">
                <div>
                    <div class="lab-title"><?php echo get_string('codinglab', 'theme_chronolms'); ?></div>
                    <div class="lab-subtitle">
                        <?php if ($isvpl): ?>
                            Write, test and improve your code &mdash; <?php echo $labtype; ?>
                        <?php elseif ($isnextblocks): ?>
                            Build programs with blocks &mdash; <?php echo $labtype; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- VPL / NextBlocks switcher tabs -->
                <div style="display:flex;gap:8px;align-items:center;">
                    <a href="<?php echo (new moodle_url('/mod/vpl/'))->out(false); ?>"
                       class="btn btn-sm <?php echo $isvpl ? 'btn-primary' : 'btn-ghost'; ?>"
                       title="Text Coding (Python, JS, etc.)">
                        <?php echo \theme_chronolms\icons::svg('code'); ?> Text Coding
                    </a>
                    <a href="<?php echo (new moodle_url('/mod/nextblocks/'))->out(false); ?>"
                       class="btn btn-sm <?php echo $isnextblocks ? 'btn-primary' : 'btn-ghost'; ?>"
                       title="Block Coding">
                        <?php echo \theme_chronolms\icons::svg('blocks'); ?> Block Coding
                    </a>
                </div>
            </div>

            <!-- The VPL or NextBlocks activity renders its full interface here -->
            <!-- Our CSS skin wraps their native editor with the Coding Lab chrome -->
            <div class="lab-body" id="lab-activity-body">
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
</button>
<div id="chronolms-chatbot-panel" role="dialog" aria-label="AI Assistant">
    <div class="chatbot-header">
        <div class="bot-avatar"><?php echo \theme_chronolms\icons::svg('bot'); ?></div>
        <div class="bot-info"><div class="bot-name">ChronoBot</div><div class="bot-status">Ask me anything about this problem</div></div>
        <button class="chatbot-close" id="chatbot-close-btn" aria-label="Close chatbot">
            <svg width="18" height="18" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <div class="chatbot-body"><div id="chronolms-chatbot-inner"></div></div>
</div>

<?php if (strpos($PAGE->url->get_path(), '/mod/nextblocks') !== false): ?>
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
<?php echo $OUTPUT->standard_end_of_body_html(); ?>
</body>
</html>
