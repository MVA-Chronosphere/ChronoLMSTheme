<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Privacy Subsystem implementation for theme_chronolms.
 *
 * @package    theme_chronolms
 * @copyright  2026 Chronosphere Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_chronolms\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;

/**
 * The theme stores one user preference: the progress last celebrated, so each celebration shows once.
 *
 * @copyright  2026 Chronosphere Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\user_preference_provider {

    /** Points, level and badges the user was last shown a celebration for. */
    const PREF_SEEN = 'theme_chronolms_seen';

    /**
     * Returns metadata about this plugin.
     *
     * @param collection $items The initialised collection to add items to.
     * @return collection
     */
    public static function get_metadata(collection $items): collection {
        $items->add_user_preference(self::PREF_SEEN, 'privacy:metadata:preference:seen');
        return $items;
    }

    /**
     * Export the user's preferences for this plugin.
     *
     * @param int $userid The user whose data is exported.
     */
    public static function export_user_preferences(int $userid) {
        $seen = get_user_preferences(self::PREF_SEEN, null, $userid);
        if ($seen !== null) {
            writer::export_user_preference('theme_chronolms', self::PREF_SEEN, $seen,
                get_string('privacy:metadata:preference:seen', 'theme_chronolms'));
        }
    }
}
