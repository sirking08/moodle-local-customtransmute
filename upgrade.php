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
 * Installation code for the local_customtransmute plugin.
 *
 * @package    local_customtransmute
 * @copyright  2025 Ezekiel Lozano <sirking08@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for local_customtransmute.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_customtransmute_upgrade($oldversion) {
    global $DB;

    // Example: initial install sets defaults and backfills shadow items.
    if ($oldversion < 2025092600) {

        // Ensure default config exists.
        if (get_config('local_customtransmute', 'minfloor') === false) {
            set_config('minfloor', 65, 'local_customtransmute');
        }

        // Scan all grade_items in the site.
        $items = $DB->get_records('grade_items', [
            'gradetype' => GRADE_TYPE_VALUE
        ]);

        foreach ($items as $item) {
            // Skip our own items.
            if ($item->itemmodule === 'local_customtransmute') {
                continue;
            }

            // Already has a shadow?
            $shadow = $DB->get_record('grade_items', [
                'courseid'     => $item->courseid,
                'itemtype'     => 'manual',
                'itemmodule'   => 'local_customtransmute',
                'iteminstance' => $item->id
            ]);

            if (!$shadow) {
                grade_update('local_customtransmute', $item->courseid,
                    'manual', 'local_customtransmute', $item->id, 0,
                    null, [
                        'itemname'   => $item->itemname . ' (Transmuted)',
                        'gradetype'  => GRADE_TYPE_VALUE,
                        'grademax'   => 100,
                        'grademin'   => 0,
                        'hidden'     => $item->hidden
                    ]);
            }
        }

        upgrade_plugin_savepoint(true, 2025092600, 'local', 'customtransmute');
    }

    return true;
}
