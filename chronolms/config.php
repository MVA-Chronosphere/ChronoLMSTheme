<?php
/**
 * ChronoLMS Theme - theme configuration.
 *
 * @package    theme_chronolms
 * @copyright  2026 Chronosphere Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$THEME->name          = 'chronolms';
$THEME->sheets        = ['chronolms'];
$THEME->editor_sheets = [];
$THEME->editor_scss   = ['editor'];
$THEME->usefallback   = true;
$THEME->requires_javascript_init = false;

// Parent theme — inherits Boost core
$THEME->parents = ['boost'];

// Layouts: map each Moodle page type to a layout file
$THEME->layouts = [

    // ── Unauthenticated / public pages ──────────────────────────────────────
    'base' => [
        'file'    => 'base.php',
        'regions' => [],
    ],
    'standard' => [
        'file'    => 'base.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'frontpage' => [
        'file'    => 'frontpage.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
        'options' => ['nonavbar' => true],
    ],
    'login' => [
        'file'    => 'login.php',
        'regions' => [],
        'options' => ['nofooter' => true, 'nonavbar' => true],
    ],

    // ── Authenticated app pages ───────────────────────────────────────────
    'mydashboard' => [
        'file'    => 'dashboard.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
        'options' => ['nonavbar' => false, 'langmenu' => true],
    ],
    'mycourses' => [
        'file'    => 'mycourses.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'course' => [
        'file'    => 'course.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'incourse' => [
        'file'    => 'course.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'coursecategory' => [
        'file'    => 'base.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'admin' => [
        'file'    => 'base.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'report' => [
        'file'    => 'base.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'mypublic' => [
        'file'    => 'base.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'popup' => [
        'file'    => 'fallback.php',
        'regions' => [],
        'options' => ['nofooter' => true, 'nonavbar' => true],
    ],
    'secure' => [
        'file'    => 'fallback.php',
        'regions' => [],
        'options' => ['nofooter' => true, 'nonavbar' => true],
    ],
    'maintenance' => [
        'file'    => 'fallback.php',
        'regions' => [],
        'options' => ['nofooter' => true, 'nonavbar' => true],
    ],
    'redirect' => [
        'file'    => 'fallback.php',
        'regions' => [],
        'options' => ['nofooter' => true, 'nonavbar' => true],
    ],
    'embedded' => [
        'file'    => 'fallback.php',
        'regions' => [],
        'options' => ['nofooter' => true, 'nonavbar' => true, 'nocoursefooter' => true],
    ],
];

// SCSS
$THEME->scss         = function($theme) { return theme_chronolms_get_main_scss_content($theme); };
$THEME->prescsscallback = 'theme_chronolms_get_pre_scss';
$THEME->extrascsscallback = 'theme_chronolms_get_extra_scss';

// Fonts / icons
$THEME->iconsystem = '\\core\\output\\icon_system_fontawesome';

// Block regions
$THEME->enable_dock   = false;
$THEME->csstree       = false;
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
