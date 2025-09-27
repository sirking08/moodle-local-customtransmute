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
 * Update/create shadow transmuted grade when a grade changes.
 *
 * @param \core\event\grade_updated $event
 */
function local_customtransmute_handle_grade_updated(\core\event\grade_updated $event): void {
    global $DB;

    $transaction = $DB->start_delegated_transaction();

    try {
        // Lock grade + item for concurrency safety
        $grade = $DB->get_record_sql(
            'SELECT * FROM {grade_grades} WHERE id = ? FOR UPDATE',
            [$event->objectid],
            MUST_EXIST
        );
        $item = $DB->get_record_sql(
            'SELECT * FROM {grade_items} WHERE id = ? FOR UPDATE',
            [$grade->itemid],
            MUST_EXIST
        );

        // Skip recursion
        if ($item->itemmodule === 'local_customtransmute') {
            debugging("Skip recursion: item {$item->id} is already a transmuted grade item.", DEBUG_DEVELOPER);
            $transaction->allow_commit();
            return;
        }

        // Only numeric items
        if ($item->gradetype != GRADE_TYPE_VALUE || $item->grademax <= 0) {
            debugging("Skip item {$item->id}: non-numeric or invalid grademax.", DEBUG_DEVELOPER);
            $transaction->allow_commit();
            return;
        }

        // Skip empty grades
        if ($grade->finalgrade === null) {
            debugging("Skip grade {$grade->id}: no final grade yet.", DEBUG_DEVELOPER);
            $transaction->allow_commit();
            return;
        }

        // Ensure shadow grade item exists
        $shadow = $DB->get_record('grade_items', [
            'courseid'     => $item->courseid,
            'itemtype'     => 'manual',
            'itemmodule'   => 'local_customtransmute',
            'iteminstance' => $item->id
        ]);

        if (!$shadow) {
            debugging("Creating shadow grade item for original item {$item->id}.", DEBUG_DEVELOPER);
            mtrace("CustomTransmute: Creating shadow for item {$item->id} ({$item->itemname})");

            grade_update('local_customtransmute', $item->courseid,
                'manual', 'local_customtransmute', $item->id, 0,
                null, [
                    'itemname'   => $item->itemname . ' (Transmuted)',
                    'gradetype'  => GRADE_TYPE_VALUE,
                    'grademax'   => 100,
                    'grademin'   => 0,
                    'gradepass'  => 75,
                    'hidden'     => $item->hidden,
                ]);
            $shadow = $DB->get_record('grade_items', [
                'courseid'     => $item->courseid,
                'itemtype'     => 'manual',
                'itemmodule'   => 'local_customtransmute',
                'iteminstance' => $item->id
            ], '*', MUST_EXIST);
        }

        // Calculate transmuted grade
        $minfloor   = (int)get_config('local_customtransmute', 'minfloor') ?: 65;
        $rawpercent = ($grade->finalgrade / $item->grademax) * 100;
        $trans      = local_customtransmute_calculate($rawpercent, 100, $minfloor);

        if ($trans !== null) {
            $trans = max(0, min(100, $trans));

            debugging("Updating transmuted grade for user {$grade->userid} → {$trans}", DEBUG_DEVELOPER);
            mtrace("CustomTransmute: User {$grade->userid} item {$item->id} → {$trans}");

            grade_update('local_customtransmute', $item->courseid,
                'manual', 'local_customtransmute', $item->id, 0,
                [
                    'userid'     => $grade->userid,
                    'rawgrade'   => $trans,
                    'finalgrade' => $trans
                ]);
        } else {
            debugging("No transmutation result for grade {$grade->id}.", DEBUG_DEVELOPER);
        }

        $transaction->allow_commit();

    } catch (Exception $e) {
        $transaction->rollback($e);
        debugging('Error in customtransmute grade update: ' . $e->getMessage(), DEBUG_DEVELOPER);
        mtrace('CustomTransmute ERROR: ' . $e->getMessage());
    }
}

/**
 * Transmutation formula.
 */
function local_customtransmute_calculate($e, $n, $minfloor = 65) {
    if ($e < 0 || $n <= 0) {
        return null;
    }

    $l = 0.6 * $n;

    if ($e >= $l) {
        $interval = 25 / ($n - $l);
        return round(100 - ($n - $e) * $interval);
    } else if ($e >= $l - 0.4 && $e < $l) {
        return 74;
    } else {
        $interval = (74 - $minfloor) / ($l - 1);
        return round(74 - $interval * ($l - 1 - $e));
    }
}

