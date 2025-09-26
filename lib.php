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
 * Event observer for grade updates.
 *
 * @param \core\event\grade_updated $event
 * @return void
 */
 function local_customtransmute_handle_grade_updated(\core\event\grade_updated $event): void {
    global $DB;

    // Get grade + item.
    $grade = $DB->get_record('grade_grades', ['id' => $event->objectid], '*', MUST_EXIST);
    $item  = $DB->get_record('grade_items', ['id' => $grade->itemid], '*', MUST_EXIST);

    // Skip if this is already a customtransmute item → prevent recursion.
    if ($item->itemmodule === 'local_customtransmute') {
        return;
    }

    // Only numeric value items.
    if ($item->gradetype != GRADE_TYPE_VALUE || $item->grademax <= 0) {
        return;
    }

    // Find (or create) shadow grade item.
    $shadowitem = $DB->get_record('grade_items', [
        'courseid'     => $item->courseid,
        'itemtype'     => 'manual',
        'itemmodule'   => 'local_customtransmute',
        'iteminstance' => $item->id
    ]);

    if (!$shadowitem) {
        $shadowitem = new stdClass();
        $shadowitem->courseid      = $item->courseid;
        $shadowitem->itemtype      = 'manual';
        $shadowitem->itemmodule    = 'local_customtransmute';
        $shadowitem->iteminstance  = $item->id;
        $shadowitem->itemname      = $item->itemname . ' (Transmuted)';
        $shadowitem->grademax      = 100;
        $shadowitem->grademin      = 0;
        $shadowitem->hidden        = $item->hidden;

        // Create via grade_update.
        grade_update('local_customtransmute', $item->courseid,
            'manual', 'local_customtransmute', $item->id, 0,
            [], ['deleted' => 0, 'itemdetails' => (array)$shadowitem]);

        // Refetch shadow item safely.
        $shadowitem = $DB->get_record('grade_items', [
            'courseid'     => $item->courseid,
            'itemtype'     => 'manual',
            'itemmodule'   => 'local_customtransmute',
            'iteminstance' => $item->id
        ], '*', MUST_EXIST);
    }

    // Calculate transmuted grade.
    $minfloor   = (int)get_config('local_customtransmute', 'minfloor') ?: 65;
    if ($grade->finalgrade === null) {
        return; // nothing to do
    }
    $rawpercent = ($grade->finalgrade / $item->grademax) * 100;
    $trans      = local_customtransmute_calculate($rawpercent, 100, $minfloor);

    if ($trans === null) {
        return;
    }

    // Insert/update grade in shadow item.
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
