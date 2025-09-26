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
 * Demo page for local_customtransmute
 *
 * @package    local_customtransmute
 * @copyright  2025 Ezekiel Lozano <sirking08@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_login();

// Check permissions
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url(new moodle_url('/local/customtransmute/demo.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('demo', 'local_customtransmute'));
$PAGE->set_heading(get_string('demo', 'local_customtransmute'));

// Get the minimum floor from settings
$minfloor = get_config('local_customtransmute', 'minfloor');
if ($minfloor === false) {
    $minfloor = 65; // Default value if not set
}

// Process form submission
$score = optional_param('score', '', PARAM_FLOAT);
$total = optional_param('total', 100, PARAM_FLOAT);
$transmuted = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($score === '' || $total === '') {
        $error = get_string('invalidinput', 'local_customtransmute');
    } elseif (!is_numeric($score) || !is_numeric($total)) {
        $error = get_string('invalidinput', 'local_customtransmute');
    } elseif ($score < 0 || $total <= 0) {
        $error = get_string('negativeinput', 'local_customtransmute');
    } elseif ($score > $total) {
        $error = get_string('scoreexceedstotal', 'local_customtransmute');
    } else {
        require_once($CFG->dirroot . '/local/customtransmute/lib.php');
        $transmuted = local_customtransmute_calculate($score, $total, $minfloor);
    }
}

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('demo', 'local_customtransmute'));

echo html_writer::tag('p', get_string('minfloordesc', 'local_customtransmute') . ": $minfloor");

// Display form
$form = new html_form();
$form->method = 'post';
$form->action = new moodle_url('/local/customtransmute/demo.php');
$form->class = 'mform';

// Form elements
$table = new html_table();
$table->attributes['class'] = 'generaltable';

// Score input
$scorecell = new html_table_cell(html_writer::label(get_string('score', 'local_customtransmute'), 'score'));
$scoreinput = html_writer::empty_tag('input', [
    'type' => 'number',
    'name' => 'score',
    'id' => 'score',
    'step' => '0.01',
    'min' => '0',
    'value' => $score !== '' ? $score : '',
    'required' => true
]);
$scorecell2 = new html_table_cell($scoreinput);

// Total items input
$totalcell = new html_table_cell(html_writer::label(get_string('totalitems', 'local_customtransmute'), 'total'));
$totalinput = html_writer::empty_tag('input', [
    'type' => 'number',
    'name' => 'total',
    'id' => 'total',
    'step' => '1',
    'min' => '1',
    'value' => $total,
    'required' => true
]);
$totalcell2 = new html_table_cell($totalinput);

// Submit button
$submitcell = new html_table_cell(html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('calculate', 'local_customtransmute'),
    'class' => 'btn btn-primary'
]));
$submitcell->colspan = 2;

// Add rows to table
$table->data = [
    [$scorecell, $scorecell2],
    [$totalcell, $totalcell2],
    [$submitcell]
];

// Display form
echo html_writer::tag('form', html_writer::table($table), [
    'method' => 'post',
    'action' => $form->action->out(false),
    'class' => 'mform'
]);

// Display result or error
if ($error) {
    echo $OUTPUT->notification($error, 'error');
} elseif ($transmuted !== null) {
    $percentage = $total > 0 ? round(($score / $total) * 100, 2) : 0;
    
    $result = html_writer::tag('h4', get_string('transmutedgrade', 'local_customtransmute') . ": $transmuted");
    $result .= html_writer::tag('p', "Score: $score / $total ($percentage%)");
    
    echo $OUTPUT->box($result, 'generalbox', 'transmutation-result');
}

echo $OUTPUT->footer();