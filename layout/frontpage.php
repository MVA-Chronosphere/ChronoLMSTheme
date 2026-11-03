<?php
/**
 * ChronoLMS frontpage layout - modern high-tech academy landing page.
 *
 * @package    theme_chronolms
 * @copyright  2026 Chronosphere Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
global $OUTPUT, $CFG, $SITE, $USER;

$maincontent = $OUTPUT->main_content();

// Resolve brand logo
try {
    $logourl = $OUTPUT->get_logo_url(null, 75);
    if (!$logourl) {
        $logourl = $OUTPUT->image_url('logo', 'theme_chronolms');
    }
} catch (\Throwable $e) {
    $logourl = $OUTPUT->image_url('logo', 'theme_chronolms');
}
try {
    $logomarkurl = $OUTPUT->image_url('logo_mark', 'theme_chronolms');
} catch (\Throwable $e) {
    $logomarkurl = '';
}

echo $OUTPUT->doctype();
?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <title><?php echo $OUTPUT->page_title(); ?></title>
    <link rel="shortcut icon" href="<?php echo $OUTPUT->favicon(); ?>" />
    <?php echo $OUTPUT->standard_head_html(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ChronoLMS - Future-Ready STEM & Programming Academy for Grades 6-12">
    <style>
        .teachers, .content .teachers, .coursebox .teachers, ul.teachers, .flex-grow-1:has(.teachers) {
            display: none !important;
        }
    </style>
</head>
<body <?php echo $OUTPUT->body_attributes(['chronolms-frontpage']); ?>>
<?php echo $OUTPUT->standard_top_of_body_html(); ?>

<!-- ChronoLMS Modern Glassmorphic Public Navbar -->
<nav class="chronolms-public-nav" role="navigation" aria-label="Main navigation">
    <div class="pub-nav-inner">
        <a href="<?php echo $CFG->wwwroot; ?>" class="pub-nav-logo" aria-label="ChronoLMS Home">
            <img src="<?php echo $logourl; ?>" alt="<?php echo format_string($SITE->fullname); ?>" class="nav-logo-img">
        </a>
        <div class="pub-nav-links d-none d-md-flex">
            <a href="#frontpage-available-course-list">Courses</a>
            <a href="#hero-features">Features</a>
            <?php echo $OUTPUT->custom_menu(); ?>
        </div>
        <div class="pub-nav-actions">
            <?php if (isloggedin() && !isguestuser()): ?>
                <a href="<?php echo new moodle_url('/my/'); ?>" class="btn-nav-cta">
                    Go to Dashboard <i class="fa fa-arrow-right ms-2"></i>
                </a>
            <?php else: ?>
                <a href="<?php echo new moodle_url('/login/index.php'); ?>" class="btn-nav-cta">
                    Login <i class="fa fa-arrow-right ms-2"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- ChronoLMS Hero Section -->
<header class="chronolms-hero-section">
    <div class="hero-glow hero-glow-1"></div>
    <div class="hero-glow hero-glow-2"></div>
    <div class="hero-container">
        <div class="hero-badge">
            <span class="badge-dot"></span>
            <span>Chronosphere STEM & Coding Academy</span>
        </div>
        <h1 class="hero-title">
            Empowering Young Minds With <span class="gradient-text">Industry-Ready Coding</span>
        </h1>
        <p class="hero-subtitle">
            Interactive Python programming, block-based algorithmic thinking, automated grading labs, and personalized learning journeys tailored for students in Grades 6–12.
        </p>
        <div class="hero-actions">
            <a href="#frontpage-available-course-list" class="btn-hero-primary">
                Explore Available Courses <i class="fa fa-chevron-down ms-2"></i>
            </a>
            <?php if (!isloggedin() || isguestuser()): ?>
            <a href="<?php echo new moodle_url('/login/index.php'); ?>" class="btn-hero-secondary">
                Login <i class="fa fa-sign-in ms-2"></i>
            </a>
            <?php else: ?>
            <a href="<?php echo new moodle_url('/my/'); ?>" class="btn-hero-secondary">
                My Dashboard <i class="fa fa-tachometer ms-2"></i>
            </a>
            <?php endif; ?>
        </div>

        <!-- Highlights Strip -->
        <div class="hero-features-strip" id="hero-features">
            <div class="feature-item">
                <div class="feature-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                </div>
                <div class="feature-text">
                    <strong>Interactive Coding Labs</strong>
                    <span>Live Python & NextBlocks</span>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                </div>
                <div class="feature-text">
                    <strong>Automated Evaluation</strong>
                    <span>Instant test execution & feedback</span>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
                </div>
                <div class="feature-text">
                    <strong>Verified Progress</strong>
                    <span>Milestones & mastery certificates</span>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                </div>
                <div class="feature-text">
                    <strong>STEM Curriculum</strong>
                    <span>Industry-aligned coursework</span>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Main Course Catalog Content -->
<main role="main" class="chronolms-frontpage-main">
    <?php echo $maincontent; ?>
</main>

<!-- Modern Public Footer -->
<footer class="chronolms-public-footer">
    <div class="footer-container">
        <div class="footer-brand">
            <img src="<?php echo $logourl; ?>" alt="ChronoLMS" class="footer-logo">
            <p class="footer-tagline">Chronosphere Academy · Empowering future software engineers and STEM innovators.</p>
        </div>
        <div class="footer-meta">
            <div class="footer-links">
                <a href="<?php echo $CFG->wwwroot; ?>">Home</a>
                <a href="#frontpage-available-course-list">Courses</a>
                <a href="<?php echo new moodle_url('/login/index.php'); ?>">Login</a>
            </div>
            <p class="footer-copyright">&copy; <?php echo date('Y'); ?> Chronosphere Academy. All rights reserved.</p>
        </div>
    </div>
</footer>

<?php echo $OUTPUT->standard_end_of_body_html(); ?>
</body>
</html>
