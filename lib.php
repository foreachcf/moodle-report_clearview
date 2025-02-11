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
 * Library of interface functions and constants.
 *
 * @package     report_clearview
 * @copyright   2024 Andrew Caya <andrewscaya@yahoo.ca>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $CFG;

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/blocks/configurable_reports/locallib.php');

/**
 * Return if the plugin supports $feature.
 *
 * @param string $feature Constant representing the feature.
 * @return true | null True if the feature is supported, null otherwise.
 */
function report_clearview_supports($feature) {
    // lib/moodlelib.php
    switch ($feature) {
        case MOD_PURPOSE_ADMINISTRATION:
            return true;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the clearview into the database.
 *
 * Given an object containing all the necessary data, (defined by the form
 * in mod_form.php) this function will create a new instance and return the id
 * number of the instance.
 *
 * @param object $moduleinstance An object from the form.
 * @param report_clearview_mod_form $mform The form.
 * @return int The id of the newly inserted record.
 */
function report_clearview_add_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->timecreated = time();

    $id = $DB->insert_record('clearview', $moduleinstance);

    return $id;
}

/**
 * Updates an instance of the clearview in the database.
 *
 * Given an object containing all the necessary data (defined in mod_form.php),
 * this function will update an existing instance with new data.
 *
 * @param object $moduleinstance An object from the form in mod_form.php.
 * @param report_clearview_mod_form $mform The form.
 * @return bool True if successful, false otherwise.
 */
function report_clearview_update_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->timemodified = time();
    $moduleinstance->id = $moduleinstance->instance;

    return $DB->update_record('clearview', $moduleinstance);
}

/**
 * Removes an instance of the clearview from the database.
 *
 * @param int $id Id of the module instance.
 * @return bool True if successful, false on failure.
 */
function report_clearview_delete_instance($id) {
    global $DB;

    $exists = $DB->get_record('clearview', array('id' => $id));

    if (!$exists) {
        return false;
    }

    $DB->delete_records('clearview', array('id' => $id));

    return true;
}

function get_current_user_courses($jsonencode = false) {
    global $USER;

    $courses = [
        'courses' => [],
        'course_completion_average' => 0,
    ];

    $coursecompletiontotal = [];
    $generator = course_get_enrolled_courses_for_logged_in_user();

    $studentroles = get_student_roles();

    foreach ($generator as $singlecourse) {
        $context = \context_course::instance($singlecourse->id);
        $roles = get_user_roles($context, $USER->id, true);
        $studentroleflag = false;

        foreach ($roles as $singlerole) {
            if (in_array($singlerole->roleid, $studentroles)) {
                $studentroleflag = true;
            }
        }

        if ($studentroleflag === true) {
            $courses['courses'][$singlecourse->id]['course_info']['id'] = $singlecourse->id;
            $courses['courses'][$singlecourse->id]['course_info']['idnumber'] = $singlecourse->idnumber;
            $courses['courses'][$singlecourse->id]['course_info']['fullname'] = $singlecourse->fullname;
            $courses['courses'][$singlecourse->id]['course_info']['category'] = $singlecourse->category;
            $courses['courses'][$singlecourse->id]['course_info']['startdate'] = $singlecourse->startdate;

            $cinfo = new \completion_info($singlecourse);
            $studentcompletions = $cinfo->get_completions($USER->id);
            $criteria = $cinfo->get_criteria();
            $iscomplete = $cinfo->is_course_complete($USER->id);

            if ($iscomplete) {
                $coursecompletiontotal[$singlecourse->id] = 100;
            } else {
                $coursecompletiontotal[$singlecourse->id] = 0; //rand(0, 100);
            }

            $courses['courses'][$singlecourse->id]['completion'] = $coursecompletiontotal[$singlecourse->id];
        }
    }

    // Multi-criteria filtering is removed, as requested by the client - ASC 2024-05-08.
    //$coursecompletiontotal = array_filter($coursecompletiontotal);

    $totalofcompletions = count($coursecompletiontotal);

    if ($totalofcompletions > 0) {
        $coursecompletionaverage = round(array_sum($coursecompletiontotal) / $totalofcompletions);
    } else {
        $coursecompletionaverage = 0;
    }

    $courses['course_completion_average'] = $coursecompletionaverage;

    return $jsonencode ? json_encode($courses) : $courses;
}

function get_current_user_categories($jsonencode = false) {
    global $USER;

    $adminflag = false;

    $admins = get_admins();

    foreach ($admins as $admin) {
        if ($USER->id === $admin->id) {
            // Admins can access anything.
            $adminflag = true;
            break;
        }
    }

    if ($adminflag) {
        $categories = \core_course_category::make_categories_list();
    } elseif (\core_course_category::has_manage_capability_on_any()) {
        $categories = \core_course_category::make_categories_list(['moodle/category:manage']);
    } else {
        // Student access
        $categories = [];
    }

    return $jsonencode ? json_encode($categories) : $categories;
}

function get_category_object(int $categoryid, $jsonencode = false) {
    $categoryobject = \core_course_category::get($categoryid);

    return $jsonencode ? json_encode($categoryobject) : $categoryobject;
}

function get_category_children(\core_course_category $categoryobject, $jsonencode = false) {
    // To get immediate children.
    $categorychildren = $categoryobject->get_children(['id' => $categoryobject->id]);

    return $jsonencode ? json_encode($categorychildren) : $categorychildren;
}

function get_category_courses(\core_course_category $categoryobject, $jsonencode = false) {
    global $DB;
    $coursestmp = $DB->get_records('course', ['category' => $categoryobject->id]);

    // Recursive for all courses in subcategories also (returns core_course_list_element - not cache compatible).
    //$coursestmp = $categoryobject->get_courses(['recursive', 'idonly']);
    //$coursestmp = $categoryobject->get_courses();

    if (!empty($coursestmp)) {
        $courses = $coursestmp;
    } else {
        $courses = [];
    }

    return $jsonencode ? json_encode($courses) : $courses;
}

function get_category_courses_with_ids(\core_course_category $categoryobject, $jsonencode = false) {
    $courses = get_category_courses($categoryobject);

    $courseids = array_keys($courses);

    $courseswithids = ['ids' => $courseids, 'courses' => $courses];

    return $jsonencode ? json_encode($courseswithids) : $courseswithids;
}

function get_extended_category_children_ids(\core_course_category $categoryobject, $jsonencode = false) {
    $subcategoriesids = $categoryobject->get_all_children_ids();

    return $jsonencode ? json_encode($subcategoriesids) : $subcategoriesids;
}

function get_student_roles($jsonencode = false) {
    global $DB;

    $studentrolestmp = $DB->get_records('role', ['archetype' => 'student'], null, 'id');

    $studentroles = [];

    foreach ($studentrolestmp as $singlestudentrole) {
        $studentroles[] = $singlestudentrole->id;
    }

    return $jsonencode ? json_encode($studentroles) : $studentroles;
}

function get_available_configurable_reports(string $type, int $courseid = SITEID) {
    $allowedtypes = [
        'global',
        'course',
    ];

    if ($courseid === 0) {
        $courseid = SITEID;
    }

    if (!in_array($type, $allowedtypes)) {
        $content = new \stdClass;
        $content->footer = '';
        $content->icons = [];

        return $content;
    }

    $configurablereportsproxy = new \report_clearview\configurable_reports_proxy();
    $configurablereportsproxy->set_courseid($courseid);
    $content = $configurablereportsproxy->get_content();
    $items = $content->items[$type];
    $content->items = $items;

    return $content;
}

function get_configurable_reports(int $courseid, int $userid, bool $allcourses = true, array $coursecategoryids = [SITEID]) {
    global $DB;

    $reports = [];

    if ($courseid === SITEID) {
        $context = \context_system::instance();
    } else {
        $context = \context_course::instance($courseid);
    }

    if (has_capability('block/configurable_reports:managereports', $context, $userid)) {
        if ($allcourses && $courseid == SITEID) {
            $reportsglobal = $DB->get_records('block_configurable_reports', ['global' => 1], 'name ASC');
            $reportssite = $DB->get_records('block_configurable_reports', ['courseid' => SITEID], 'name ASC');
            $reports = [];

            if (isset($reportsglobal) && is_array($reportsglobal)) {
                $reports = $reportsglobal;
            }

            if (isset($reportssite) && is_array($reportssite)) {
                $reports = $reports + $reportssite;
            }
        } elseif ($allcourses && $courseid != SITEID) {
            $reports = $DB->get_records('block_configurable_reports', ['global' => 1], 'name ASC');
        } else {
            $reports = $DB->get_records('block_configurable_reports', ['courseid' => $courseid], 'name ASC');
        }
    } else {
        $reports = $DB->get_records_select('block_configurable_reports', 'ownerid = ? AND courseid = ? ORDER BY name ASC', [$userid, $courseid]);
    }

    foreach ($reports as $key => $singlereport) {
        if ($singlereport->courseid !== SITEID && !in_array($singlereport->courseid, $coursecategoryids)) {
            unset($reports[$key]);
        }
    }

    return $reports;
}

function print_configurable_reports(array $reports, int $courseid = 1) {
    global $CFG, $DB, $USER, $OUTPUT;

    $table = new \stdclass;
    $table->width = "100%";
    $table->head = [
        get_string('name'),
        get_string('reportsmanage', 'admin') . ' - ' . get_string('course'),
        get_string('type', 'block_configurable_reports'),
        get_string('download', 'block_configurable_reports'),
    ];
    $table->align = ['left', 'left', 'left', 'left', 'center'];
    $table->size = ['30%', '30%', '10%', '10%', '20%'];
    $strexport = get_string('exportreport', 'block_configurable_reports');

    $editcell = '';

    foreach ($reports as $r) {
        if ($r->courseid == SITEID) {
            $coursename = '<a target="_blank" href="' . $CFG->wwwroot . '">' . get_string('site') . '</a>';
        } else if (!$coursename = $DB->get_field('course', 'fullname', ['id' => $r->courseid])) {
            $coursename = get_string('deleted');
        } else {
            $coursename = format_string($coursename);
            $coursename = '<a target="_blank" href="'.$CFG->wwwroot.'/blocks/configurable_reports/managereport.php?courseid='.$r->courseid.'">'.$coursename.'</a>';
        }

        $editcell .= '<a target="_blank" title="'.$strexport.'" href="/blocks/configurable_reports/export.php?id='.$r->id.'&amp;sesskey='.$USER->sesskey.'">'.
            $OUTPUT->pix_icon('t/backup', $strexport).
            '</a>&nbsp;&nbsp;';

        $download = '';
        $export = explode(',', $r->export);
        if (!empty($export)) {
            foreach ($export as $e) {
                if ($e) {
                    $download .= '<a target="_blank" href="/blocks/configurable_reports/viewreport.php?id='.$r->id.'&amp;download=1&amp;format='.$e.'">'.
                        '<img src="'.$CFG->wwwroot.'/blocks/configurable_reports/export/'.$e.'/pix.gif" alt="'.$e.'">'.
                        '&nbsp;'.(strtoupper($e)).'</a>&nbsp;&nbsp;';
                }
            }
        }

        if ($r->global == 1 && $courseid != 1) {
            $reportlink = '<a target="_blank" href="/blocks/configurable_reports/viewreport.php?id='.$r->id.'&courseid='.$courseid.'">'.format_string($r->name).'</a>';
        } else {
            $reportlink = '<a target="_blank" href="/blocks/configurable_reports/viewreport.php?id='.$r->id.'">'.format_string($r->name).'</a>';
        }

        $table->data[] = [
            $reportlink,
            $coursename,
            get_string('report_'.$r->type, 'block_configurable_reports'),
            $download
        ];
    }

    $table->id = 'configurable-reports-list';

    return cr_print_table($table, true);
}

/**
 * Renders an HTML table.
 *
 * This method may modify the passed instance by adding some default properties if they are not set yet.
 * If this is not what you want, you should make a full clone of your data before passing them to this
 * method. In most cases this is not an issue at all so we do not clone by default for performance
 * and memory consumption reasons.
 *
 * @param html_table $table data to be rendered
 * @return string HTML code
 */
function print_html_table(html_table $t) {
    $output = '';

    if ($t->responsive) {
        $output .= '<div class="table-responsive">';
    }

    $output .= '<table id="' . $t->id . '" class="' . $t->attributes['class'] . '">';
    $output .= '<thead>';
    $output .= '<tr>';

    foreach ($t->head as $header) {
        $output .= '<th>' . $header . '</th>';
    }

    $output .= '</tr>';
    $output .= '</thead>';

    $output .= '<tbody>';

    if (!empty($t->data)) {
        foreach ($t->data as $row) {
            if (isset($row->id)) {
                $output .= '<tr id="'. $row->id . '">';
            } else {
                $output .= '<tr>';
            }

            foreach ($row->cells as $cell) {
                if (isset($cell->id)) {
                    $output .= '<td id="'. $cell->id . '">';
                } else {
                    $output .= '<td>';
                }

                $output .= $cell->text;

                $output .= '</td>';
            }

            $output .= '</tr>';
        }
    }

    $output .= '</tbody>';

    $output .= '</table>';

    if ($t->responsive) {
        $output .= '</div>';
    }

    return $output;
}

function filter_control_chars(string $input) {
    // Strip control characters from input string.
    // preg_replace('/[\x00-\x1F\x7F]/', '', $input);
    // preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
    return preg_replace('/[[:cntrl:]]/', '', $input);
}

function build_category_data(int $categoryid, string $categoryfqn) {
    global $CFG, $PAGE, $OUTPUT;

    require_once($CFG->libdir . '/completionlib.php');

    $categorydata = [];
    $courses = [];
    $currentstudents = [];
    $categorycompletiontotalarray = [];
    $categorycompletionaverage = 0;

    $categoryobject = get_category_object($categoryid);

    $categoryname = $categoryobject->name;

    // To get immediate children.
    // $children = get_category_children($categoryid);

    //$coursestmp = get_category_courses($categoryobject);

    $courseswithids = get_category_courses_with_ids($categoryobject);

    if (!empty($courseswithids['ids']) && !empty($courseswithids['courses'])) {
        foreach ($courseswithids['courses'] as $courseobject) {
            $currentstudentstmp = [];

            $courses[$courseobject->id]['course_info']['id'] = $courseobject->id;
            $courses[$courseobject->id]['course_info']['idnumber'] = $courseobject->idnumber;
            $courses[$courseobject->id]['course_info']['fullname'] = trim(filter_control_chars($courseobject->fullname));
            $courses[$courseobject->id]['course_info']['category'] = trim(filter_control_chars($courseobject->category));
            $courses[$courseobject->id]['course_info']['startdate'] = $courseobject->startdate;

            $context = \context_course::instance($courseobject->id);
            $courseusers = get_enrolled_users($context, '', 0, '*');

            $studentroles = get_student_roles();

            foreach ($courseusers as $key => $singlestudent) {
                if ($singlestudent->suspended != 1 && $singlestudent->deleted != 1) {
                    $roles = get_user_roles($context, $singlestudent->id, true);

                    $studentroleflag = false;

                    foreach ($roles as $singlerole) {
                        if (in_array($singlerole->roleid, $studentroles)) {
                            $studentroleflag = true;
                        }
                    }

                    if ($studentroleflag === true) {
                        $cinfo = new \completion_info($courseobject);
                        $iscomplete = $cinfo->is_course_complete($singlestudent->id);

                        $userpicture = new \user_picture($singlestudent);
                        $url = $userpicture->get_url($PAGE, $OUTPUT);

                        $currentstudentstmp[$singlestudent->id]['student_info']['id'] = $singlestudent->id;
                        $currentstudentstmp[$singlestudent->id]['student_info']['idnumber'] = $singlestudent->idnumber;
                        $currentstudentstmp[$singlestudent->id]['student_info']['firstname'] = trim(filter_control_chars($singlestudent->firstname));
                        $currentstudentstmp[$singlestudent->id]['student_info']['lastname'] = trim(filter_control_chars($singlestudent->lastname));
                        $currentstudentstmp[$singlestudent->id]['student_info']['email'] = $singlestudent->email;
                        $currentstudentstmp[$singlestudent->id]['picture_url'] = $url->out();
                        $currentstudentstmp[$singlestudent->id]['roles'] = json_decode(json_encode($roles), true);

                        if ($iscomplete) {
                            $completiontotal = 100;
                        } else {
                            $completiontotal = 0; //rand(0, 100);
                        }

                        $currentstudentstmp[$singlestudent->id]['completion'][$courseobject->id] = [
                            'courseid' => $courseobject->id,
                            'iscomplete' => $iscomplete,
                            'completiontotal' => $completiontotal,
                        ];

                        if (!isset($currentstudents[$singlestudent->id])) {
                            $currentstudents[$singlestudent->id]['student_info']['id'] = $singlestudent->id;
                            $currentstudents[$singlestudent->id]['student_info']['idnumber'] = $singlestudent->idnumber;
                            $currentstudents[$singlestudent->id]['student_info']['firstname'] = trim(filter_control_chars($singlestudent->firstname));
                            $currentstudents[$singlestudent->id]['student_info']['lastname'] = trim(filter_control_chars($singlestudent->lastname));
                            $currentstudents[$singlestudent->id]['student_info']['email'] = $singlestudent->email;
                            $currentstudents[$singlestudent->id]['picture_url'] = $url->out();
                            $currentstudents[$singlestudent->id]['roles'] = json_decode(json_encode($roles), true);
                        }

                        $currentstudents[$singlestudent->id]['completion'][$courseobject->id] = [
                            'courseid' => $courseobject->id,
                            'iscomplete' => $iscomplete,
                            'completiontotal' => $completiontotal,
                        ];
                    }
                }
            }

            $courses[$courseobject->id]['students'] = $currentstudentstmp;
        }

        foreach ($courseswithids['courses'] as $courseobject) {
            $coursecompletiontotalarray = [];
            $singleusercompletionsarray = [];

            foreach ($currentstudents as $singlestudent) {
                foreach ($singlestudent['completion'] as $courseid => $completiondata) {
                    if ($courseid == $courseobject->id) {
                        $coursecompletiontotalarray[] = $completiondata['completiontotal'];
                    }
                }
            }

            $totalofcoursecompletions = count($coursecompletiontotalarray);

            if ($totalofcoursecompletions > 0) {
                $courses[$courseobject->id]['course_completion_average'] =
                    round(array_sum($coursecompletiontotalarray) / $totalofcoursecompletions);
            } else {
                $courses[$courseobject->id]['course_completion_average'] = 0;
            }

            $categorycompletiontotalarray[] = $courses[$courseobject->id]['course_completion_average'];
        }

        foreach ($currentstudents as $singlestudentid => $singlestudent) {
            foreach ($singlestudent['completion'] as $completiondata) {
                $singleusercompletionsarray[$singlestudentid][] = $completiondata['completiontotal'];
            }
        }

        foreach ($singleusercompletionsarray as $studentid => $singleusercompletions) {
            $totalsinglestudentcompletions = count($singleusercompletions);

            if ($totalsinglestudentcompletions > 0) {
                $currentstudents[$studentid]['completion_average'] = round(array_sum($singleusercompletions) / $totalsinglestudentcompletions);
            } else {
                $currentstudents[$studentid]['completion_average'] = 0;
            }
        }

        $totalofcategorycompletions = count($categorycompletiontotalarray);

        if ($totalofcategorycompletions > 0) {
            $categorycompletionaverage = round(array_sum($categorycompletiontotalarray) / $totalofcategorycompletions);
        }
    }

    ksort($currentstudents);

    $categorydata[$categoryid] = [
        'id' => $categoryid,
        'fqn' => $categoryfqn,
        'name' => $categoryname,
        'courses' => $courses,
        'course_ids' => $courseswithids['ids'],
        'students' => $currentstudents,
        'completion_average' => $categorycompletionaverage,
    ];

    return $categorydata;
}

function filter_category_students(array $categorydata, array $roleids) {
    foreach ($categorydata['courses'] as $courseid => $singlecourse) {
        foreach ($singlecourse['students'] as $studentid => $singlestudent) {
            $rolefoundflag = false;

            foreach ($singlestudent['roles'] as $studentrole) {
                if (in_array($studentrole['roleid'], $roleids)) {
                    $rolefoundflag = true;
                    break;
                }
            }

            if (!$rolefoundflag) {
                unset($categorydata['courses'][$courseid]['students'][$studentid]);
            }
        }
    }

    foreach ($categorydata['students'] as $studentid => $singlestudent) {
        $rolefoundflag = false;

        foreach ($singlestudent['roles'] as $studentrole) {
            if (in_array($studentrole['roleid'], $roleids)) {
                $rolefoundflag = true;
                break;
            }
        }

        if (!$rolefoundflag) {
            unset($categorydata['students'][$studentid]);
        }
    }

    $categorycompletiontotalarray = [];

    foreach ($categorydata['students'] as $singlestudent) {
        $categorycompletiontotalarray[] = $singlestudent['completion_average'];
    }

    $totalofcategorycompletions = count($categorycompletiontotalarray);

    if ($totalofcategorycompletions > 0) {
        $categorycompletionaverage = round(array_sum($categorycompletiontotalarray) / $totalofcategorycompletions);
    } else {
        $categorycompletionaverage = 0;
    }

    $categorydata['completion_average'] = $categorycompletionaverage;

    return $categorydata;
}

function get_main_page(int $categoryid = 0, bool $extended = false, int $reporttype = 2) {
    global $USER;

    $categoriesall = get_current_user_categories();

    $adminflag = false;

    $admins = get_admins();

    foreach ($admins as $admin) {
        if ($USER->id === $admin->id) {
            // Admins can access anything.
            $adminflag = true;
            break;
        }
    }

    if (!$adminflag) {
        $numberoftenancymanagers = (int) (get_config('report_clearview', 'numberoftenancymanagers') ?: 1);

        $tenancymanagerroles = [];

        for ($i = 1; $i <= $numberoftenancymanagers; $i++) {
            $tenancymanagerroles[$i] = get_config('report_clearview', 'tmauthorityrole' . $i);
        }

        $usercategoryroles = get_user_roles(\context_coursecat::instance($categoryid), $USER->id);
    }

    if (!empty($categoriesall)) {
        if ($categoryid === 0 || !array_key_exists($categoryid, $categoriesall)) {
            $categoryid = array_key_first($categoriesall);
        }

        $courses = [];
        $currentstudents = [];

        $coursecatcache = \cache::make('report_clearview', 'categorydatacache');
        $basecachekey = 'report_clearview_catcache_' . $categoryid;
        $categorydatapayload = $coursecatcache->get($basecachekey);

        if (!empty($categorydatapayload)) {
            $categorydata = unserialize($categorydatapayload);
        } else {
            if ($extended) {
                $url = new \moodle_url(
                    '/report/clearview/index.php',
                    [
                        'rpt' => $reporttype,
                        'id' => $categoryid,
                        'ext' => 0
                    ]
                );

                redirect(
                    $url,
                    get_string('nocachefound', 'report_clearview'),
                    null,
                    \core\output\notification::NOTIFY_INFO
                );
            }
        }

        if ($categorydata === null) {
            $categorydata = build_category_data($categoryid, $categoriesall[$categoryid]);
        }

        if (!$adminflag) {
            $categorydatatmp = $categorydata[$categoryid];

            foreach ($usercategoryroles as $usercategoryroleobject) {
                if (in_array($usercategoryroleobject->roleid, $tenancymanagerroles)) {
                    $key = array_search($usercategoryroleobject->roleid, $tenancymanagerroles);
                    $tenancysubordinateroles = explode(',', get_config('report_clearview', 'tmmappedroles' . $key));

                    $categorydatatmp = filter_category_students($categorydatatmp, $tenancysubordinateroles);
                }
            }

            $categorydata[$categoryid] = $categorydatatmp;
        }

        $courses = $courses + $categorydata[$categoryid]['courses'];
        $currentstudents = $currentstudents + $categorydata[$categoryid]['students'];

        if ($extended) {
            $corecoursecategory = \core_course_category::get($categoryid, IGNORE_MISSING, true, $USER->id);

            if (!empty($corecoursecategory)) {
                $subcategories = get_extended_category_children_ids($corecoursecategory);

                sort($subcategories);

                foreach ($subcategories as $childcategoryid) {
                    $childcategorydata = null;

                    $basecachekey = 'report_clearview_catcache_' . $childcategoryid;
                    $childcategorydatapayload = $coursecatcache->get($basecachekey);

                    if (!empty($childcategorydatapayload)) {
                        $childcategorydata = unserialize($childcategorydatapayload);
                    }

                    if ($childcategorydata === null) {
                        $childcategorydata = build_category_data($childcategoryid, $categoriesall[$childcategoryid]);
                    }

                    foreach ($childcategorydata[$childcategoryid]['students'] as $childcategorysinglestudentkey => $childcategorysinglestudent) {
                        if ($currentstudents[$childcategorysinglestudentkey]['completion'] !== null
                            && $childcategorysinglestudent['completion'] !== null
                        ) {
                            $currentstudents[$childcategorysinglestudentkey]['completion'] =
                                array_merge(
                                    $currentstudents[$childcategorysinglestudentkey]['completion'],
                                    $childcategorysinglestudent['completion']
                                );
                        }
                    }

                    if (!$adminflag) {
                        $categorydatatmp = $childcategorydata[$childcategoryid];

                        foreach ($usercategoryroles as $usercategoryroleobject) {
                            if (in_array($usercategoryroleobject->roleid, $tenancymanagerroles)) {
                                $key = array_search($usercategoryroleobject->roleid, $tenancymanagerroles);
                                $tenancysubordinateroles = explode(',', get_config('report_clearview', 'tmmappedroles' . $key));

                                $categorydatatmp = filter_category_students($categorydatatmp, $tenancysubordinateroles);
                            }
                        }

                        $childcategorydata[$childcategoryid] = $categorydatatmp;
                    }

                    $categorydata = $categorydata + $childcategorydata;
                    $courses = $courses + $childcategorydata[$childcategoryid]['courses'];
                    $currentstudents = $currentstudents + $childcategorydata[$childcategoryid]['students'];
                }

                $categorycompletiontotalarray = [];

                foreach ($categorydata as $singlecategory) {
                    if (!empty($singlecategory['courses'])) {
                        $categorycompletiontotalarray[] = $singlecategory['completion_average'];
                    }
                }

                $totalofcategorycompletions = count($categorycompletiontotalarray);

                if ($totalofcategorycompletions > 0) {
                    $categorycompletionaverage = round(array_sum($categorycompletiontotalarray) / $totalofcategorycompletions);
                } else {
                    $categorycompletionaverage = 0;
                }
            }
        } else {
            $categorycompletionaverage = $categorydata[$categoryid]['completion_average'];
        }

        ksort($currentstudents);

        $studentcompletiontotalarray = [];

        if (!empty($currentstudents)) {
            foreach ($currentstudents as $singlecurrentstudent) {
                if (!empty($singlecurrentstudent['completion_average'])) {
                    $studentcompletiontotalarray[] = $singlecurrentstudent['completion_average'];
                } else {
                    $studentcompletiontotalarray[] = 0;
                }
            }
        }

        $totalofstudentcompletions = count($studentcompletiontotalarray);

        if ($totalofstudentcompletions > 0) {
            $studentcompletionaverage = round(array_sum($studentcompletiontotalarray) / $totalofstudentcompletions);
        } else {
            $studentcompletionaverage = 0;
        }

        $categorydata[$categoryid]['all_category_courses'] = $courses;

        $categorydata[$categoryid]['all_category_students'] = $currentstudents;

        $categorydata['all_students_completion_average'] = $studentcompletionaverage;

        $categorydata['all_category_completion_average'] = $categorycompletionaverage;
    }

    return $categorydata;
}

function get_csv_export(array $data, int $categoryid, bool $extended = false, int $reporttype = 2) {
    global $CFG, $USER;

    $matrix = [];

    if ($reporttype === 2) {
        $matrix[0] = [
            get_string('tableid', 'report_clearview'),
            get_string('tabletitle', 'report_clearview'),
            get_string('tablecategory', 'report_clearview'),
            get_string('tablenumberofenrolled', 'report_clearview'),
            get_string('tablecompletionrate', 'report_clearview'),
        ];

        if (!empty($data[$categoryid]['all_category_courses'])) {
            $i = 0;

            foreach ($data[$categoryid]['all_category_courses'] as $courseobjects) {
                $idnumber = !empty($courseobjects['course_info']['idnumber']) ? $courseobjects['course_info']['idnumber'] : 'N/A';

                $matrix[$i + 1][] = str_replace("\r", ' ', str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br($idnumber)))));
                $matrix[$i + 1][] = str_replace("\r", ' ', str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br($courseobjects['course_info']['fullname'])))));
                $matrix[$i + 1][] = str_replace("\r", ' ', str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br($data[$courseobjects['course_info']['category']]['name'])))));
                $matrix[$i + 1][] = str_replace("\r", ' ', str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br(count($courseobjects['students']))))));
                $matrix[$i + 1][] = $courseobjects['course_completion_average'] ?: 0;

                $i++;
            }
        }
    } elseif ($reporttype === 3) {
        $matrix[0] = [
            get_string('tableid', 'report_clearview'),
            get_string('tablefullname', 'report_clearview'),
            get_string('tableemail', 'report_clearview'),
            get_string('tablenumberofcourses', 'report_clearview'),
            get_string('tablecompletionrate', 'report_clearview'),
        ];

        if (!empty($data[$categoryid]['all_category_students'])) {
            $i = 0;

            foreach ($data[$categoryid]['all_category_students'] as $studentsobjects) {
                $idnumber =
                    !empty($studentsobjects['student_info']['idnumber']) ? $studentsobjects['student_info']['idnumber'] : 'N/A';

                $matrix[$i + 1][] =
                    str_replace("\r", ' ', str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br($idnumber)))));
                $matrix[$i + 1][] = str_replace("\r", ' ', str_replace("\n", ' ',
                    htmlspecialchars_decode(strip_tags(nl2br($studentsobjects['student_info']['firstname'] . ' ' .
                        $studentsobjects['student_info']['lastname'])))));
                $matrix[$i + 1][] = str_replace("\r", ' ', str_replace("\n", ' ',
                    htmlspecialchars_decode(strip_tags(nl2br($studentsobjects['student_info']['email']))))) ?: 'N/A';
                $matrix[$i + 1][] = !empty($studentsobjects['completion']) ? count($studentsobjects['completion']) : 0;
                $matrix[$i + 1][] = $studentsobjects['completion_average'] ?: 0;

                $i++;
            }
        }
    }

    $filename = 'clearview_csvreport_' . $USER->id . '_' . substr(bin2hex(random_bytes(32)), 0, 32);
    make_temp_directory('csvimport' . DIRECTORY_SEPARATOR . $USER->id);
    $path = $CFG->tempdir . DIRECTORY_SEPARATOR . 'csvimport' . DIRECTORY_SEPARATOR . $USER->id . DIRECTORY_SEPARATOR . $filename;

    // Check to see if the file exists. If so, delete it.
    if (file_exists($path)) {
        unlink($path);
    }

    $fp = fopen($path, 'w+');

    // Windows UTF-8 (UTF-16LE).
    //fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));

    if (defined('PHP_VERSION_ID') && PHP_VERSION_ID > 80100) {
        foreach ($matrix as $row) {
            // Compatible with PHP 8.1+.
            fputcsv($fp, $row, ";", "\"", "\\", "\r\n");
        }
    } else {
        foreach ($matrix as $row) {
            // Compatible with previous PHP versions.
            fputcsv($fp, $row, ";", "\"", "\\");
        }
    }

    fseek($fp, 0); // Same as rewind.

    $dataelements = '';

    // Windows UTF-8 (UTF-16LE).
    $dataelements .= chr(0xEF) . chr(0xBB) . chr(0xBF);

    while (($content = fgets($fp)) !== false) {
        $dataelements .= $content;
    }

    fclose($fp);

    return $dataelements;
}

/**
 * Is a given scale used by the instance of clearview?
 *
 * This function returns if a scale is being used by one clearview
 * if it has support for grading and scales.
 *
 * @param int $moduleinstanceid ID of an instance of this module.
 * @param int $scaleid ID of the scale.
 * @return bool True if the scale is used by the given clearview instance.
 */
function report_clearview_scale_used($moduleinstanceid, $scaleid) {
    global $DB;

    if ($scaleid && $DB->record_exists('clearview', array('id' => $moduleinstanceid, 'grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Checks if scale is being used by any instance of clearview.
 *
 * This is used to find out if scale used anywhere.
 *
 * @param int $scaleid ID of the scale.
 * @return bool True if the scale is used by any clearview instance.
 */
function report_clearview_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid && $DB->record_exists('clearview', array('grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Creates or updates grade item for the given clearview instance.
 *
 * Needed by {@see grade_update_mod_grades()}.
 *
 * @param stdClass $moduleinstance Instance object with extra cmidnumber and modname property.
 * @param bool $reset Reset grades in the gradebook.
 * @return void.
 */
function report_clearview_grade_item_update($moduleinstance, $reset=false) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    $item = array();
    $item['itemname'] = clean_param($moduleinstance->name, PARAM_NOTAGS);
    $item['gradetype'] = GRADE_TYPE_VALUE;

    if ($moduleinstance->grade > 0) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax']  = $moduleinstance->grade;
        $item['grademin']  = 0;
    } else if ($moduleinstance->grade < 0) {
        $item['gradetype'] = GRADE_TYPE_SCALE;
        $item['scaleid']   = -$moduleinstance->grade;
    } else {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }
    if ($reset) {
        $item['reset'] = true;
    }

    grade_update('/report/clearview', $moduleinstance->course, 'mod', 'clearview', $moduleinstance->id, 0, null, $item);
}

/**
 * Delete grade item for given clearview instance.
 *
 * @param stdClass $moduleinstance Instance object.
 * @return grade_item.
 */
function report_clearview_grade_item_delete($moduleinstance) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('/report/clearview', $moduleinstance->course, 'mod', 'clearview',
                        $moduleinstance->id, 0, null, array('deleted' => 1));
}

/**
 * Update clearview grades in the gradebook.
 *
 * Needed by {@see grade_update_mod_grades()}.
 *
 * @param stdClass $moduleinstance Instance object with extra cmidnumber and modname property.
 * @param int $userid Update grade of specific user only, 0 means all participants.
 */
function report_clearview_update_grades($moduleinstance, $userid = 0) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    // Populate array of grade objects indexed by userid.
    $grades = array();
    grade_update('/report/clearview', $moduleinstance->course, 'mod', 'clearview', $moduleinstance->id, 0, $grades);
}

/**
 * Returns the lists of all browsable file areas within the given module context.
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by {@see file_browser::get_file_info_context_module()}.
 *
 * @package     clearview
 * @category    files
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return string[].
 */
function report_clearview_get_file_areas($course, $cm, $context) {
    return array();
}

/**
 * File browsing support for clearview file areas.
 *
 * @package     clearview
 * @category    files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info Instance or null if not found.
 */
function report_clearview_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    return null;
}

/**
 * Serves the files from the clearview file areas.
 *
 * @package     clearview
 * @category    files
 *
 * @param stdClass $course The course object.
 * @param stdClass $cm The course module object.
 * @param stdClass $context The clearview's context.
 * @param string $filearea The name of the file area.
 * @param array $args Extra arguments (itemid, path).
 * @param bool $forcedownload Whether or not force download.
 * @param array $options Additional options affecting the file serving.
 */
function report_clearview_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options = array()) {
    global $DB, $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }

    require_login($course, true, $cm);
    send_file_not_found();
}

/**
 * This function extends the course category navigation with the report items.
 *
 * @param navigation_node $parentnode
 * @param context_coursecat $context
 *
 * @return navigation_node
 */
function report_clearview_extend_navigation_category_settings($parentnode, $context) {
    $capabilities = [
        'moodle/category:manage',
        'moodle/course:create',
    ];

    if (has_all_capabilities($capabilities, $context)
    ) {
        $parentnode->add(
            get_string('modulenameplural', 'report_clearview'),
            new moodle_url('/report/clearview/index.php', ['rpt' => 2, 'id' => $context->instanceid]),
            navigation_node::TYPE_SETTING,
            null,
            null,
            new pix_icon('i/report', get_string('modulenameplural', 'report_clearview'))
        );
    };
}

/**
 * This function extends the course navigation with the report items.
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course to object for the report
 * @param stdClass $context The context of the course
 */
function report_clearview_extend_navigation_course($navigation, $course, $context) {
    global $DB;

    $capabilities = [
        'moodle/category:manage',
        'moodle/course:create',
    ];

    if ($course->category) {
        $category = $DB->get_record('course_categories', ['id' => $course->category], '*', MUST_EXIST);
        $catcontext = \context_coursecat::instance($category->id);

        if (has_all_capabilities($capabilities, $catcontext)) {
            $url = new moodle_url('/report/clearview/coursereports.php', ['id' => $course->id]);

            $navigation->add(
                get_string('modulenameplural', 'report_clearview'),
                $url,
                navigation_node::TYPE_SETTING,
                null,
                null,
                new pix_icon('i/report', get_string('modulenameplural', 'report_clearview')));
        }
    }
}

/**
 * This function extends the course navigation with the report items.
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $user
 * @param stdClass $course The course to object for the report
 */
function report_clearview_extend_navigation_user($navigation, $user, $course) {
    global $DB;

    $capabilities = [
        'moodle/category:manage',
        'moodle/course:create',
    ];

    if ($course->id != 1) {
        $category = $DB->get_record('course_categories', ['id' => $course->category], '*', MUST_EXIST);
        $catcontext = \context_coursecat::instance($category->id);

        if (report_clearview_can_access_user_report($user, $course)
            && has_all_capabilities($capabilities, $catcontext)
        ) {
            $url = new moodle_url('/report/clearview/index.php', ['rpt' => 3, 'id' => $course->id]);
            $navigation->add(get_string('modulenameplural', 'report_clearview'), $url);
        }
    }
}

/**
 * Is current user allowed to access this report.
 *
 * @private defined in lib.php for performance reasons
 *
 * @param stdClass $user
 * @param stdClass $course
 * @return bool
 */
function report_clearview_can_access_user_report($user, $course) {
    global $USER;

    $coursecontext = context_course::instance($course->id);
    $personalcontext = context_user::instance($user->id);

    if ($user->id == $USER->id) {
        if ($course->showreports and (is_viewing($coursecontext, $USER) or is_enrolled($coursecontext, $USER))) {
            return true;
        }
    } else if (has_capability('moodle/user:viewuseractivitiesreport', $personalcontext)) {
        if ($course->showreports and (is_viewing($coursecontext, $user) or is_enrolled($coursecontext, $user))) {
            return true;
        }

    }

    // Check if $USER shares group with $user (in case separated groups are enabled and 'moodle/site:accessallgroups' is disabled).
    if (!groups_user_groups_visible($course, $user->id)) {
        return false;
    }

    if (has_capability('report/outline:viewuserreport', $coursecontext)) {
        return true;
    }

    return false;
}

/**
 * Return a list of page types.
 *
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 *
 * @return array
 */
function report_clearview_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $array = array(
        '*'                    => get_string('page-x', 'pagetype'),
        'report-*'             => get_string('page-report-x', 'pagetype'),
        'report-outline-*'     => get_string('page-report-outline-x',  'report_outline'),
        'report-outline-index' => get_string('page-report-outline-index',  'report_outline'),
        'report-outline-user'  => get_string('page-report-outline-user',  'report_outline')
    );
    return $array;
}

/**
 * Add nodes to the myprofile page.
 *
 * @param \core_user\output\myprofile\tree $tree Tree object
 * @param stdClass $user user object
 * @param bool $iscurrentuser
 * @param stdClass $course Course object
 *
 * @return bool
 */
function report_clearview_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    if ($iscurrentuser) {
        if (empty($course)) {
            // We want to display these reports under the site context.
            $course = get_fast_modinfo(SITEID)->get_course();
        }

        $url = new moodle_url('/report/clearview/index.php', ['rpt' => 1]);
        $node = new core_user\output\myprofile\node('reports', 'clearviewreports', get_string('modulenameplural', 'report_clearview'), null, $url);
        $tree->add_node($node);
    }
}
