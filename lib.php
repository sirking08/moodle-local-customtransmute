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
 * Helper logger for CustomTransmute.
 *
 * @param string $message
 * @param bool   $iserror
 */
function local_customtransmute_log(string $message, bool $iserror = false): void {
    $enabled   = (bool)get_config('local_customtransmute', 'enablelogging');
    $logtofile = (bool)get_config('local_customtransmute', 'logtofile');

    if (!$enabled) {
        return;
    }

    $prefix = $iserror ? "CustomTransmute ERROR: " : "CustomTransmute: ";
    $fullmsg = $prefix . $message;

    // Standard debugging + CLI output.
    debugging($fullmsg, DEBUG_DEVELOPER);
    mtrace($fullmsg);

    // Optional file logging.
    if ($logtofile) {
        $logdir = make_writable_directory($CFG->dataroot . '/local_customtransmute_logs');
        $logfile = $logdir . '/transmute.log';

        $line = '[' . date('Y-m-d H:i:s') . '] ' . $fullmsg . PHP_EOL;
        file_put_contents($logfile, $line, FILE_APPEND | LOCK_EX);
    }
}

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
            local_customtransmute_log("Skip recursion: item {$item->id} already transmuted.");
            $transaction->allow_commit();
            return;
        }

        // Only numeric items
        if ($item->gradetype != GRADE_TYPE_VALUE || $item->grademax <= 0) {
            local_customtransmute_log("Skip item {$item->id}: non-numeric or invalid grademax.");
            $transaction->allow_commit();
            return;
        }

        // Skip empty grades
        if ($grade->finalgrade === null) {
            local_customtransmute_log("Skip grade {$grade->id}: no final grade yet.");
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
            local_customtransmute_log("Creating shadow grade item for original item {$item->id} ({$item->itemname}).");

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

            local_customtransmute_log("Updating user {$grade->userid} grade: {$trans}");

            grade_update('local_customtransmute', $item->courseid,
                'manual', 'local_customtransmute', $item->id, 0,
                [
                    'userid'     => $grade->userid,
                    'rawgrade'   => $trans,
                    'finalgrade' => $trans
                ]);
        } else {
            local_customtransmute_log("No transmutation result for grade {$grade->id}.");
        }

        $transaction->allow_commit();

    } catch (Exception $e) {
        $transaction->rollback($e);
        local_customtransmute_log('Error: ' . $e->getMessage(), true);
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
