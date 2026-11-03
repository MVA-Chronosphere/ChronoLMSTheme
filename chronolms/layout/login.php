<?php
/**
 * ChronoLMS login layout - preserves existing custom login page design.
 *
 * @package    theme_chronolms
 * @copyright  2026 Chronosphere Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
global $OUTPUT, $CFG, $SITE;

$maincontent = $OUTPUT->main_content();

$logourl = $OUTPUT->get_logo_url(null, 75);
if (!$logourl) {
    $logourl = $OUTPUT->image_url('logo', 'theme_chronolms');
}

echo $OUTPUT->doctype();
?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <title><?php echo $OUTPUT->page_title(); ?></title>
    <link rel="shortcut icon" href="<?php echo $OUTPUT->favicon(); ?>" />
    <?php echo $OUTPUT->standard_head_html(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ChronoLMS - Empowering Students from Grades 6-12 with Industry-Ready Programming Skills">
    <style>
        .teachers, .content .teachers, .coursebox .teachers, ul.teachers, .flex-grow-1:has(.teachers) {
            display: none !important;
        }
    </style>
</head>
<body <?php echo $OUTPUT->body_attributes(['chronolms-login']); ?>>
<?php echo $OUTPUT->standard_top_of_body_html(); ?>

<?php echo $OUTPUT->render_from_template('theme_chronolms/login/page', [
    'formcontent' => $maincontent,
    'wwwroot'     => $CFG->wwwroot,
    'logourl'     => $logourl->out(false),
    'sitename'    => format_string($SITE->fullname),
]); ?>

<?php echo $OUTPUT->standard_end_of_body_html(); ?>
</body>
</html>
