<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname'   => '\core\event\grade_item_created',
        'callback'    => 'local_customtransmute_handle_grade_item_created',
        'includefile' => '/local/customtransmute/lib.php',
        'internal'    => false,
        'priority'    => 9999,
    ],
    [
        'eventname'   => '\core\event\grade_updated',
        'callback'    => 'local_customtransmute_handle_grade_updated',
        'includefile' => '/local/customtransmute/lib.php',
        'internal'    => false,
        'priority'    => 9999,
    ],
];
