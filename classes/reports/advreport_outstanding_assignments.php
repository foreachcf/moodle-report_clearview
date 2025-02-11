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
 * Advanced Report class for clearview report.
 *
 * @package     report_clearview
 * @copyright   2024 Andrew Caya <andrewscaya@yahoo.ca>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_clearview\reports;

defined('MOODLE_INTERNAL') || die;

/**
 * Advanced Report class for clearview report.
 *
 * This class is the Clearview plugin's advreport_outstanding_assignments class.
 */
class advreport_outstanding_assignments extends advanced_report {

    /** Sets the context level for the report. Default is CONTEXT_SYSTEM */
    const CONTEXTLEVEL = CONTEXT_COURSE;

    /**
     * Build the two-dimensional array contained in $this->reportdata.
     *
     * @return array|bool
     */
    protected function build_report() {
        global $CFG, $DB;

        $prefix = $CFG->prefix;

        $sql = "SELECT DISTINCT
            a.id AS 'ID',
            u.lastname AS 'Lastname',
            u.firstname AS 'Firstname',
            u.idnumber AS 'Idnumber',
            c.fullname AS 'Course',
            a.name AS 'Assignment',
            DATE_FORMAT(FROM_UNIXTIME(a.gradingduedate),'%Y-%m-%d %H:%i') AS 'Due_date_UTC'
            
            FROM {user_enrolments} ue
            JOIN {enrol} AS e ON e.id = ue.enrolid
            JOIN {course} AS c ON c.id = e.courseid
            JOIN {user} AS u ON u.id = ue.userid
            JOIN {assign} a ON a.course = c.id
            WHERE 
            # pick your course but make sure it agrees with the c.id in the subselect
            c.id = " . $this->courseid . "
            # skip future dates
            AND DATEDIFF(NOW(), FROM_UNIXTIME(a.gradingduedate)) > 0 
            # only users who have not submitted 
            AND ue.userid NOT IN 
             (SELECT asub.userid
              FROM {assign_submission} AS asub
              JOIN {assign} AS a ON a.id = asub.assignment 
              JOIN {course} c on a.course = c.id
              WHERE c.id = " . $this->courseid . ")
            
            ORDER BY u.username, c.shortname";

        $rawdata = $DB->get_records_sql($sql) ?? [];

        $this->reportdata = $this->hydrate_to_array($rawdata);

        return $this->reportdata;
    }
}
