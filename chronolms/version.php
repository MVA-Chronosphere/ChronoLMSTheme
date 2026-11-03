<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * ChronoLMS Theme - version definition.
 *
 * @package    theme_chronolms
 * @copyright  2026 Chronosphere Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2026110300;
$plugin->requires  = 2022112800;   // Moodle 4.1 or later.
$plugin->component = 'theme_chronolms';
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.5.0';
$plugin->dependencies = ['theme_boost' => ANY_VERSION];
