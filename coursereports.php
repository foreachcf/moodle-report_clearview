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
 * Prints a list of reports related to a course (both advanced Clearview reports and third-party).
 *
 * @package     report_clearview
 * @copyright   2024 Andrew Caya <andrewscaya@yahoo.ca>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/config/config.php');
require_once(__DIR__ . '/lib.php');

global $SITE, $PAGE, $CFG, $DB, $USER, $OUTPUT;

define('REPORT_TYPE_PERSONAL', 1);
define('REPORT_TYPE_COURSE', 2);
define('REPORT_TYPE_USER', 3);

$id = required_param('id', PARAM_INT);
$catid = optional_param('catid', 0, PARAM_INT);
$extendedcategory = optional_param('ext', 0, PARAM_INT);

$tenantwwwroot = $CFG->wwwroot;

$iscategorymanager = false;

$context = \context_course::instance($id);
$data['context_id'] = $context->id;
$contextid = $data['context_id'];

// Login to the course and retrieve also all fields defined by course format.
$course = get_course($id);

require_login($course);

$course = course_get_format($course)->get_course();

$reporttype = REPORT_TYPE_COURSE;

$reporttypenamesarray = [
    REPORT_TYPE_PERSONAL => get_string('myreports', 'report_clearview'),
    REPORT_TYPE_COURSE => get_string('courses', 'report_clearview'),
    REPORT_TYPE_USER => get_string('users', 'report_clearview'),
];

$reporttypename = $reporttypenamesarray[$reporttype];

$category = $DB->get_record('course_categories', ['id' => $course->category], '*', MUST_EXIST);
$categoryid = $category->id;
$catcontext = \context_coursecat::instance($category->id);
//\core_course_category::has_capability_on_any('moodle/category:manage')
//has_capability('moodle/category:manage', $catcontext)
require_capability('moodle/category:manage', $catcontext);
require_capability('report/courseoverview:view', $context);

$usercategories = get_current_user_categories(false);

if (empty($usercategories)) {
    if ($reporttype !== 1) {
        $reporttype = REPORT_TYPE_PERSONAL;
        $url = new moodle_url('/report/clearview/index.php', ['rpt' => $reporttype]);
        redirect($url);
    }

    $userenrolledcourses = get_current_user_courses();
} else {
    $iscategorymanager = true;
    $usercategoriestmp = $usercategories;
    ksort($usercategoriestmp);
    $categoryid = $categoryid !== 0 ? $categoryid : array_key_first($usercategoriestmp);

    //if (isset($catcontext)) {
    //    $PAGE->set_secondary_active_tab('categorymain');
    //}
}

$url = new moodle_url('/report/clearview/coursereports.php', ['id' => $course->id]);
$title = get_string('pluginname', 'report_clearview');

if ($context->contextlevel == CONTEXT_SYSTEM) {
    $heading = $SITE->fullname;
} else if ($context->contextlevel == CONTEXT_COURSECAT || $context->contextlevel == CONTEXT_COURSE) {
    $heading = $context->get_context_name();
} else {
    throw new coding_exception(get_string('unknowncontext', 'report_clearview'));
}

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_title($title);
$PAGE->set_pagelayout('incourse');
$PAGE->set_pagetype('course-view-' . $course->format);

// Set CSS.
$PAGE->requires->css('/report/clearview/css/bootstrap.min.css');
$PAGE->requires->css('/report/clearview/js/DataTables/datatables.min.css');
$PAGE->requires->css('/report/clearview/css/dashboard.css');
$PAGE->requires->css('/report/clearview/css/style.css');
$PAGE->requires->css('/report/clearview/css/coursereports.css');

// Set JS.
//$PAGE->requires->js('/report/clearview/js/main.min.js');
$PAGE->requires->js('/report/clearview/js/coursereports.js');

// Check if there is at least one displayable report.
$hasreports = false;

if ($reportnode = $PAGE->settingsnav->find('coursereports', \navigation_node::TYPE_CONTAINER)) {
    foreach ($reportnode->children as $child) {
        if ($child->display) {
            $hasreports = true;
            break;
        }
    }
}

ob_start();

if ($hasreports) {
    echo $OUTPUT->render_from_template('core/report_link_page', ['node' => $reportnode]);
} else {
    echo html_writer::div($OUTPUT->notification(get_string('noreports', 'debug'), 'error'), 'mt-3');
}

$data['corereports'] = ob_get_clean();
$corereports = $data['corereports'];


// Get Configurable Reports.
$greports = get_configurable_reports($course->id, $USER->id, true);
$creports = get_configurable_reports($course->id, $USER->id, false, [$course->id]);
$crreports = $greports + $creports;
$data['crreportstable'] = print_configurable_reports($crreports, $course->id);
$crreportstable = $data['crreportstable'];

// Get Clearview advanced reports and format the HTML table.
$t = new \html_table();
$t->id = 'clearview-reports-list';
$t->attributes['class'] = 'table table-striped table-sm';
$t->class = $t->attributes['class'];
$t->head = [
    get_string('tableid', 'report_clearview'),
    get_string('tabletitle', 'report_clearview'),
    get_string('tablereports', 'report_clearview'),
    get_string('tableexportcsv', 'report_clearview'),
    get_string('tableexportxlsx', 'report_clearview')
];

foreach ($CFG->clearview['reports'] as $reportkey => $report) {
    $classname = '\\report_clearview\\reports\\' . $report['classname'];

    if ($classname::CONTEXTLEVEL === CONTEXT_COURSE
        && $classname::CONTEXTLEVEL === $context->contextlevel
        && ($classname::$courseid == 1 || $classname::$courseid == $course->id)
        && ($classname::WWWROOT === '*' || preg_match('/.*' . $classname::WWWROOT . '.*/', $tenantwwwroot))
    ) {
        $row = new \html_table_row();
        $cell1 = new \html_table_cell();
        $cell1->text = $reportkey;
        $cell2 = new \html_table_cell();
        $cell2->text = $report['title'][current_language()];
        $cell3 = new \html_table_cell();
        $cell3->text = '<a href="/report/clearview/advreports.php?id='
            . $course->id
            . '&reportid='
            . $reportkey
            . '" target="_blank">'
            . get_string('view', 'report_clearview')
            . '</a>';
        $cell4 = new \html_table_cell();
        $cell4->text = '<a href="/report/clearview/advreports.php?id='
            . $course->id
            . '&reportid='
            . $reportkey
            . '&csv=1'
            . '" target="_blank">'
            . '<i class="fa-solid fa-file-csv"></i>'
            . '</a>';
        $cell5 = new \html_table_cell();
        $cell5->text = '<a href="/report/clearview/advreports.php?id='
            . $course->id
            . '&reportid='
            . $reportkey
            . '&xlsx=1'
            . '" target="_blank">'
            . '<i class="fas fa-file-excel"></i>'
            . '</a>';
        $row->cells[] = $cell1;
        $row->cells[] = $cell2;
        $row->cells[] = $cell3;
        $row->cells[] = $cell4;
        $row->cells[] = $cell5;
        $t->data[] = $row;
    }
}

$data['advreportstable'] = print_html_table($t);
$advreportstable = $data['advreportstable'];

// Build the 'Back' navigation button.
ob_start();
require_once(__DIR__ . '/templates/partials/crnavigation.phtml');
$navigation = ob_get_clean();

// Build the partial template.
ob_start();
require_once(__DIR__ . '/templates/partials/coursereports.phtml');
$data['main'] = ob_get_clean();

// Get the renderer and build the page.
$output = $PAGE->get_renderer('report_clearview');

echo $output->header();

echo $navigation;

//$PAGE->set_heading($course->fullname);
echo $output->heading(strtoupper($course->shortname) . ' : ' . $course->fullname);

echo $output->render_report('report_clearview/coursereports', $data);

echo $output->footer();
