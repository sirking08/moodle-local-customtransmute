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
 * @copyright  2025 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Hook function to modify the grade calculation.
 *
 * @param stdClass $grade_grade The grade_grade object being updated.
 * @param stdClass $grade_item The grade_item object.
 * @param bool $is_update Whether this is an update or an insert.
 * @return bool True if the grade was modified, false otherwise.
 */
function local_customtransmute_grade_item_update($grade_grade, $grade_item, $is_update) {
    global $CFG;
    
    // Only process if this is a manual grade item or a module that uses raw grades
    if ($grade_item->itemtype == 'manual' || $grade_item->itemtype == 'mod') {
        $rawgrade = $grade_grade->rawgrade;
        $rawgrademax = $grade_item->grademax;
        
        // Skip if rawgrade is not set or is already a percentage
        if ($rawgrade === null || $rawgrademax <= 0) {
            return false;
        }
        
        // Get the minimum floor from settings
        $minfloor = get_config('local_customtransmute', 'minfloor');
        if ($minfloor === false) {
            $minfloor = 65; // Default value if not set
        }
        
        // Calculate the percentage
        $percentage = ($rawgrade / $rawgrademax) * 100;
        
        // Apply custom transmutation
        $transmuted = local_customtransmute_calculate($percentage, 100, $minfloor);
        
        if ($transmuted !== null) {
            // Update the final grade with the transmuted value
            $grade_grade->finalgrade = $transmuted;
            return true;
        }
    }
    
    return false;
}

/**
 * Calculate the transmuted grade based on the raw score and total items.
 *
 * @param float $e The raw score
 * @param float $n The maximum possible score
 * @param int $minfloor The minimum grade floor (default: 65)
 * @return float|null The transmuted grade or null if invalid input
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
        // 60% and above → 75-100
        $interval = 25 / ($n - $l);
        // return round(100 - ($n - $e) * $interval, 2);
        // Return whole number grade (no decimals).
        return round(100 - ($n - $e) * $interval);
    } else if ($e >= $l - 0.4 && $e < $l) {
        // Just below 60% → 74
        return 74;
    } else {
        // 0-59% → floor-74
        $interval = (74 - $minfloor) / ($l - 1);
        // return round(74 - $interval * ($l - 1 - $e), 2);
        // Return whole number grade (no decimals).
        return round(74 - $interval * ($l - 1 - $e));
    }
}

// Register the grade update hook
$CFG->hooks_callback['grade_item_update'][] = 'local_customtransmute_grade_item_update';
