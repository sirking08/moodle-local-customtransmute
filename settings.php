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
// along with Moodle.  If not, see <http://www.gnu.org/copyleft/gpl.html>.

/**
 * Plugin settings for the local_customtransmute plugin.
 *
 * @package    local_customtransmute
 * @copyright  2025 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_customtransmute', get_string('pluginname', 'local_customtransmute'));
    $ADMIN->add('localplugins', $settings);

    // Add settings page to the local plugin section
    $settings->add(new admin_setting_heading(
        'local_customtransmute_settings',
        '',
        get_string('settings')
    ));

    // Add minimum grade floor setting
    $settings->add(new admin_setting_configtext(
        'local_customtransmute/minfloor',
        get_string('minfloor', 'local_customtransmute'),
        get_string('minfloordesc', 'local_customtransmute'),
        65,
        PARAM_INT
    ));

    // Add a link to the demo page
    $settings->add(new admin_setting_heading(
        'local_customtransmute_demo',
        '',
        html_writer::link(
            new moodle_url('/local/customtransmute/demo.php'),
            get_string('demo', 'local_customtransmute'),
            ['class' => 'btn btn-secondary']
        )
    ));
}