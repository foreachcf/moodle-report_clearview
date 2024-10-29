<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Prints an instance of clearview reports.
 *
 * @package     report_clearview
 * @copyright   2024 Andrew Caya <andrewscaya@yahoo.ca>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/config/config.php');
require_once(__DIR__ . '/lib.php');

global $SITE, $PAGE, $CFG, $DB, $USER;

define('REPORT_TYPE_PERSONAL', 1);
define('REPORT_TYPE_COURSE', 2);
define('REPORT_TYPE_USER', 3);

require_login();

$categoryid = optional_param('id', 0, PARAM_INT);
$reporttype = optional_param('rpt', 0, PARAM_INT);
$extendedcategory = optional_param('ext', 0, PARAM_INT);
$csvexport = optional_param('csv', 0, PARAM_INT);

$tenantwwwroot = $CFG->wwwroot;

$iscategorymanager = false;

$userenrolledcourses = [];

$usercategories = get_current_user_categories(false);

if (empty($usercategories)) {
    if ($reporttype !== 1) {
        $reporttype = REPORT_TYPE_PERSONAL;
        $url = new moodle_url('/report/clearview/index.php', ['rpt' => $reporttype]);
        redirect($url);
    }

    $context = \context_user::instance($USER->id);
    $url = new moodle_url('/report/clearview/index.php', ['rpt' => $reporttype]);
    $PAGE->set_pagelayout('mydashboard');
} else {
    $iscategorymanager = true;

    $usercategoriestmp = $usercategories;
    ksort($usercategoriestmp);

    $categoryidnew = $categoryid != 0 ? $categoryid : array_key_first($usercategoriestmp);

    $category = $DB->get_record('course_categories', ['id' => $categoryidnew], '*', MUST_EXIST);
    $context = \context_coursecat::instance($category->id);

    require_capability('moodle/category:manage', $context);
    require_capability('moodle/course:create', $context);

    $url = new moodle_url('/report/clearview/index.php',
        [
            'id' => $categoryidnew,
            'rpt' => $reporttype,
            'ext' => $extendedcategory,
        ]
    );

    if ($reporttype > 1 && $categoryid == 0) {
        redirect($url);
    } else {
        $categoryid = $categoryidnew;
    }

    $PAGE->set_pagelayout('coursecategory');

    //if (isset($catcontext)) {
    //    $PAGE->set_secondary_active_tab('categorymain');
    //}
}

$reporttype = $reporttype > 0 && $reporttype < 4 ? $reporttype : REPORT_TYPE_PERSONAL;

$reporttypenamesarray = [
    REPORT_TYPE_PERSONAL => get_string('myreports', 'report_clearview'),
    REPORT_TYPE_COURSE => get_string('courses', 'report_clearview'),
    REPORT_TYPE_USER => get_string('users', 'report_clearview'),
];

$reporttypename = $reporttypenamesarray[$reporttype];

$title = get_string('pluginname', 'report_clearview');

if ($context->contextlevel == CONTEXT_SYSTEM) {
    $heading = $SITE->fullname;
} else if ($context->contextlevel == CONTEXT_COURSECAT) {
    $heading = $context->get_context_name();
} else {
    //throw new coding_exception(get_string('unknowncontext', 'report_clearview'));
    $heading = $reporttypename;
}

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_title($title);
//$PAGE->set_heading($heading);

// Set CSS.
$PAGE->requires->css('/report/clearview/css/bootstrap.min.css');
$PAGE->requires->css('/report/clearview/js/DataTables/datatables.min.css');
$PAGE->requires->css('/report/clearview/css/bootstrap-toggle.css');
$PAGE->requires->css('/report/clearview/css/dashboard.css');
$PAGE->requires->css('/report/clearview/css/style.css');

// Set JS.
$PAGE->requires->js('/report/clearview/js/main.min.js');
$PAGE->requires->js('/report/clearview/js/index.js');
$PAGE->requires->js('/report/clearview/js/copyclip.js');

//$event = \report_clearview\event\clearview_module_viewed::create(['contextid' => $context->id]); // 11
//$event->trigger();
//exit;

$output = $PAGE->get_renderer('report_clearview');

$userenrolledcourses = get_current_user_courses();

$data['category_id'] = $categoryid;
$data['report_type'] = $reporttype;
$data['context_id'] = $context->id;
$data['user_categories'] = $usercategories;
$data['user_enrolled_courses'] = $userenrolledcourses;

if ($reporttype !== 1) {
    $data['category_data'] = get_main_page($categoryid, $extendedcategory, $reporttype);
    $data['globalreports'] = '';
}

if ($csvexport && empty($data['category_data'])) {
    header("HTTP/1.1 404 Not Found", true, 404);
} elseif ($csvexport && !empty($data['category_data'])) {
    $exportedcategorynamearray = explode('/', $usercategories[$categoryid]);

    $exportedcategoryname = strtolower(trim(end($exportedcategorynamearray)));

    $extendedcategoryname = $extendedcategory ? strtolower(get_string('navtitleextendedcategory', 'report_clearview')) : '';

    $extendedcategoryname = empty($extendedcategoryname) ? $extendedcategoryname : '_' . $extendedcategoryname;

    $filename = strtolower(get_string('pluginname', 'report_clearview'))
    . '_'
    . $exportedcategoryname
    . $extendedcategoryname;

    $filename = str_replace(' ', '_', $filename) . '_' . (time());

    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Content-Type: text/csv; charset=utf-8', true, 200);
    echo get_csv_export($data['category_data'], $categoryid, $extendedcategory, $reporttype);
} else {
    if ($reporttype !== 1 && !empty($data['category_data'])) {
        $data['globalreports'] = print_configurable_reports(get_configurable_reports(SITEID, $USER->id, true, $data['category_data'][$categoryid]['course_ids']));
    }

    $contextid = $data['context_id'];
    $categorydata = $data['category_data'];
    $globalreports = $data['globalreports'];

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

        if ($classname::CONTEXTLEVEL === CONTEXT_SYSTEM
            && ($classname::WWWROOT === '*'
                || preg_match('/.*' . $classname::WWWROOT . '.*/', $tenantwwwroot))
        ) {
            $row = new \html_table_row();
            $row->class = '';
            $cell1 = new \html_table_cell();
            $cell1->class = '';
            $cell1->text = $reportkey;
            $cell2 = new \html_table_cell();
            $cell2->class = '';
            $cell2->text = $report['title'][current_language()];
            $cell3 = new \html_table_cell();
            $cell3->class = '';
            $cell3->text = '<a href="/report/clearview/advreports.php?id=' . SITEID . '&reportid=' . $reportkey . '" target="_blank">' . get_string('view', 'report_clearview') . '</a>';
            $cell4 = new \html_table_cell();
            $cell4->class = '';
            $cell4->text = '<a href="/report/clearview/advreports.php?id=' . SITEID . '&reportid=' . $reportkey . '&csv=1" target="_blank"><i class="fa-solid fa-file-csv"></i></a>';
            $cell5 = new \html_table_cell();
            $cell5->class = '';
            $cell5->text = '<a href="/report/clearview/advreports.php?id=' . SITEID . '&reportid=' . $reportkey . '&xlsx=1" target="_blank"><i class="fas fa-file-excel"></i></a>';
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

    ob_start();

    if ($reporttype === REPORT_TYPE_PERSONAL) {
        require_once(__DIR__ . '/templates/partials/index.phtml');
    } elseif ($reporttype === REPORT_TYPE_COURSE) {
        require_once(__DIR__ . '/templates/partials/course.phtml');
    } elseif ($reporttype === REPORT_TYPE_USER) {
        require_once(__DIR__ . '/templates/partials/user.phtml');
    }

    $data['main'] = ob_get_clean();

    echo $output->header();
    echo $output->heading($title);
    //$page = new \report_clearview\output\report($context);
    echo $output->render_report('report_clearview/index', $data);
    echo $output->footer();
}
