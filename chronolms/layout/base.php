<?php
/**
 * ChronoLMS base layout — used for all authenticated pages with sidebar.
 *
 * @package    theme_chronolms
 * @copyright  2026 Chronosphere Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $PAGE, $OUTPUT, $USER, $CFG, $SITE;

$maincontent = $OUTPUT->main_content();
theme_chronolms_setup_page();
$pluginblocks = theme_chronolms_render_plugin_blocks();

// Build sidebar nav data
$renderedsidebar = $OUTPUT->render_from_template('theme_chronolms/nav/sidebar', theme_chronolms_get_sidebar_data());

// Topbar data
$topbardata = theme_chronolms_get_topbar_data();

// Is the user authenticated? Add body class
$bodyclass = 'chronolms-authenticated';

echo $OUTPUT->doctype();
?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <title><?php echo $OUTPUT->page_title(); ?></title>
    <link rel="shortcut icon" href="<?php echo $OUTPUT->favicon(); ?>" />
    <?php echo $OUTPUT->standard_head_html(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body <?php echo $OUTPUT->body_attributes(['chronolms-authenticated']); ?>>

<?php echo $OUTPUT->standard_top_of_body_html(); ?>

<!-- Mobile sidebar overlay -->
<div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>

<!-- App shell -->
<div class="chronolms-app" id="page">

    <!-- Left sidebar navigation -->
    <?php echo $renderedsidebar; ?>

    <!-- Content wrapper -->
    <div class="chronolms-content-wrapper" id="chronolms-content-wrapper">

        <!-- Topbar -->
        <?php echo $OUTPUT->render_from_template('theme_chronolms/nav/topbar', $topbardata); ?>

        <!-- Main content -->
        <main class="chronolms-main" id="page-content" role="main">
            <section id="region-main" aria-label="<?php echo get_string('content'); ?>">
            <?php
            echo theme_chronolms_get_lab_header();
            echo theme_chronolms_get_secondary_navigation();

            if ($PAGE->url && strpos($PAGE->url->get_path(), '/local/code_analytics') !== false) {
                $cid = optional_param('courseid', 0, PARAM_INT);
                $exec_url = new moodle_url('/local/code_analytics/index.php', $cid > 1 ? ['courseid' => $cid] : []);
                $stud_url = new moodle_url('/theme/chronolms/progress.php', $cid > 1 ? ['id' => $cid] : []);
                ?>
                <div class="analytics-view-switcher" style="display:flex; gap:12px; margin-bottom:20px; align-items:center;">
                    <a href="<?php echo $exec_url->out(false); ?>" class="view-switch-btn active" style="display:inline-flex; align-items:center; gap:8px; padding:10px 18px; border-radius:12px; background:#1565c0; color:#fff; text-decoration:none; font-size:13px; font-weight:600; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                        <span>Executive Cohort Analytics</span>
                    </a>
                    <a href="<?php echo $stud_url->out(false); ?>" class="view-switch-btn" style="display:inline-flex; align-items:center; gap:8px; padding:10px 18px; border-radius:12px; background:#fff; border:1.5px solid #dde5f0; color:#2d3f60; text-decoration:none; font-size:13px; font-weight:600; transition:all 0.2s;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span>Student Learning Journey</span>
                    </a>
                </div>
                <?php
            }

            echo $maincontent;
            ?>
            </section>
        </main>

    </div><!-- /.chronolms-content-wrapper -->

</div><!-- /.chronolms-app -->

<div class="chronolms-plugin-blocks" hidden><?php echo $pluginblocks; ?></div>

<!-- Floating chatbot toggle -->
<button id="chronolms-chatbot-toggle" aria-label="Open AI assistant" title="Ask AI">
    <svg width="24" height="24" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    <span class="chat-badge" id="chat-badge"></span>
</button>

<!-- Floating chatbot panel -->
<div id="chronolms-chatbot-panel" aria-hidden="true" role="dialog" aria-label="AI Assistant">
    <div class="chatbot-header">
        <div class="bot-avatar"><?php echo \theme_chronolms\icons::svg('bot'); ?></div>
        <div class="bot-info">
            <div class="bot-name">ChronoBot</div>
            <div class="bot-status">Powered by AI · Always ready</div>
        </div>
        <button class="chatbot-close" id="chatbot-close-btn" aria-label="Close chatbot">
            <svg width="18" height="18" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <div class="chatbot-body">
        <?php
        // Render the OpenAI Chat block inside the panel if it exists
        if (core_plugin_manager::instance()->get_plugin_info('block_openai_chat')) {
            $chatblock = $PAGE->blocks->get_blocks_for_region('side-pre');
            // Block is rendered in the floating panel via AMD js / direct block output
        }
        ?>
        <div id="chronolms-chatbot-inner"></div>
    </div>
</div>

<?php echo $OUTPUT->standard_end_of_body_html(); ?>

</body>
</html>
