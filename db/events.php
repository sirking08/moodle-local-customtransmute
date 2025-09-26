<?php

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\grade_updated',
        'callback'  => 'local_customtransmute_handle_grade_updated',
        'priority'  => 9999   // run after the module
    ],
];
