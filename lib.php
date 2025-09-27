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
 * Library of functions for local_customtransmute.
 *
 * @package    local_customtransmute
 * @copyright  2025 Ezekiel Lozano <sirking08@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Create a shadow transmuted grade item when a new grade item is created.
 *
 * @param \core\event\grade_item_created $event
 */
function local_customtransmute_handle_grade_item_created(\core\event\grade_item_created $event): void {
    global $DB;

    $item = $DB->get_record('grade_items', ['id' => $event->objectid], '*', MUST_EXIST);

    // Skip recursion — don’t create a shadow for our own items.
    if ($item->itemmodule === 'local_customtransmute') {
        return;
    }

    // Only apply to numeric grade items.
    if ($item->gradetype != GRADE_TYPE_VALUE || $item->grademax <= 0) {
        return;
    }

    // Ensure a shadow doesn't already exist.
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

/**
 * Update the transmuted grade when a grade value changes.
 *
 * @param \core\event\grade_updated $event
 */
function local_customtransmute_handle_grade_updated(\core\event\grade_updated $event): void {
    global $DB;

    $grade = $DB->get_record('grade_grades', ['id' => $event->objectid], '*', MUST_EXIST);
    $item  = $DB->get_record('grade_items', ['id' => $grade->itemid], '*', MUST_EXIST);

    if ($item->itemmodule === 'local_customtransmute') {
        return; // skip recursion
    }

    if ($item->gradetype != GRADE_TYPE_VALUE || $item->grademax <= 0) {
        return;
    }

    // Make sure the shadow exists (safety check).
    local_customtransmute_handle_grade_item_created(
        (object)['objectid' => $item->id]
    );

    // Fetch shadow.
    $shadow = $DB->get_record('grade_items', [
        'courseid'     => $item->courseid,
        'itemtype'     => 'manual',
        'itemmodule'   => 'local_customtransmute',
        'iteminstance' => $item->id
    ], '*', MUST_EXIST);

    if ($grade->finalgrade === null) {
        return;
    }

    // Apply transmutation.
    $minfloor   = (int)get_config('local_customtransmute', 'minfloor') ?: 65;
    $rawpercent = ($grade->finalgrade / $item->grademax) * 100;
    $trans      = local_customtransmute_calculate($rawpercent, 100, $minfloor);

    if ($trans === null) {
        return;
    }

    grade_update('local_customtransmute', $item->courseid,
        'manual', 'local_customtransmute', $item->id, 0,
        [
            'userid'     => $grade->userid,
            'rawgrade'   => $trans,
            'finalgrade' => $trans
        ]);
}


/**
 * Calculate the transmuted grade based on the raw score and total items.
 *
 * @param float $e Raw score
 * @param float $n Maximum possible score (normally 100)
 * @param int   $minfloor Minimum grade floor (default: 65)
 * @return float|null
 */
function local_customtransmute_calculate($e, $n, $minfloor = 65) {
	// Input validation
    if ($e < 0 || $n <= 0) {
        return null;
    }

    // Calculate the 60% threshold
    $l = 0.6 * $n;

    // Apply the transmutation formula
    if ($e >= $l) {
        // 60% and above → 75–100
        $interval = 25 / ($n - $l);
        return round(100 - ($n - $e) * $interval);
    } else if ($e >= $l - 0.4 && $e < $l) {
        // Just below 60% → fixed 74
        return 74;
    } else {
        // 0–59% → minfloor–74
        $interval = (74 - $minfloor) / ($l - 1);
        return round(74 - $interval * ($l - 1 - $e));
    }
}
