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
 * Prints an instance of a clearview advanced report.
 *
 * @package     report_clearview
 * @copyright   2024 Andrew Caya <andrewscaya@yahoo.ca>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/config/config.php');

global $SITE, $PAGE, $CFG, $DB;

$id = required_param('id', PARAM_INT);
$reportid = required_param('reportid', PARAM_INT);
$csvexport = optional_param('csv', 0, PARAM_INT);
$xlsxexport = optional_param('xlsx', 0, PARAM_INT);

if ($id == SITEID) {
    $context = \context::instance_by_id($id);

    require_login();

    $course = new \stdClass();
    $course->id = 1;
} else {
    $context = \context_course::instance($id);

    // Login to the course and retrieve also all fields defined by course format.
    $course = get_course($id);
    require_login($course);
    $course = course_get_format($course)->get_course();

    $category = $DB->get_record('course_categories', ['id' => $course->category], '*', MUST_EXIST);
    // \core_course_category::has_capability_on_any('moodle/category:manage')
}

require_capability('report/courseoverview:view', $context);

$url = new moodle_url('/report/clearview/advreports.php', ['id' => $id, 'reportid' => $reportid]);
$title = get_string('pluginname', 'report_clearview');

$PAGE->set_url($url);
$PAGE->set_title($title);

$PAGE->set_context($context);

if ($context->contextlevel == CONTEXT_SYSTEM) {
    $heading = $SITE->fullname;
    $PAGE->set_pagelayout('admin');
} else if ($context->contextlevel == CONTEXT_COURSECAT || $context->contextlevel == CONTEXT_COURSE) {
    $heading = $context->get_context_name();
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_pagetype('course-view-' . $course->format);
} else {
    throw new coding_exception(get_string('unknowncontext', 'report_clearview'));
}

// Set CSS.
$PAGE->requires->css('/report/clearview/css/bootstrap.min.css');
$PAGE->requires->css('/report/clearview/js/DataTables/datatables.min.css');
$PAGE->requires->css('/report/clearview/css/dashboard.css');
$PAGE->requires->css('/report/clearview/css/style.css');

// Set JS.
//$PAGE->requires->js('/report/clearview/js/main.min.js');
$PAGE->requires->js('/report/clearview/js/advreports.js');
$PAGE->requires->js('/report/clearview/js/copyclip.js');

$output = $PAGE->get_renderer('report_clearview');

$currentlanguage = current_language();

$tenantwwwroot = $CFG->wwwroot;

if (array_key_exists($reportid, $CFG->clearview['reports'])) {
    $classname = '\\report_clearview\\reports\\' . $CFG->clearview['reports'][$reportid]['classname'];

    if (($classname::CONTEXTLEVEL === CONTEXT_SYSTEM
            || $classname::CONTEXTLEVEL === CONTEXT_COURSE)
        && $classname::CONTEXTLEVEL === $context->contextlevel
        && ($classname::WWWROOT === '*'
            || preg_match('/.*' . $classname::WWWROOT . '.*/', $tenantwwwroot))
    ) {
        $advreport = new $classname(
            $reportid,
            $CFG->clearview['reports'][$reportid]['headers'],
            $course->id,
            $CFG->clearview['reports'][$reportid]['title'][current_language()]
        );

        if ($csvexport) {
            $filename = strtolower(get_string('pluginname', 'report_clearview'))
                . '_'
                . strtolower($CFG->clearview['reports'][$reportid]['title'][$currentlanguage]);

            $filename = str_replace(' ', '_', $filename) . '_' . (time());

            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            header('Content-Type: text/csv; charset=utf-8', true, 200);
            echo $advreport->export_to_csv();
        } elseif ($xlsxexport) {
            echo $advreport->export_to_xlsx();
        } else {
            echo $output->header();

            // Moved to template 'templates/partials/advreportsexport.phtml'
            //echo $output->heading($CFG->clearview['reports'][$reportid]['title'][$currentlanguage]);

            $PAGE->set_heading($CFG->clearview['reports'][$reportid]['title'][$currentlanguage]);

            ob_start();
            require_once(__DIR__ . '/templates/partials/advreportsshareexport.phtml');
            $advreportsexport = ob_get_clean();

            echo $advreportsexport;

            echo $output->render_advanced_report($advreport);

            // Build the translation strings for JS.
            ob_start();
            require_once(__DIR__ . '/templates/partials/advreportsjs.phtml');
            $jstranslations = ob_get_clean();

            echo $jstranslations;

            echo $output->footer();
        }
    } else {
        throw new \Exception(get_string('noreportinncontext', 'report_clearview'));
    }
} else {
    throw new \Exception(get_string('noreportfound', 'report_clearview'));
}
