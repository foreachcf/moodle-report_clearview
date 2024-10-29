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
 * Configurable Reports proxy class for report_clearview.
 *
 * @package     report_clearview
 * @copyright   2024 Andrew Caya <andrewscaya@yahoo.ca>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_clearview;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
require_once($CFG->dirroot . '/blocks/configurable_reports/locallib.php');
require_once($CFG->dirroot . '/blocks/configurable_reports/block_configurable_reports.php');

/**
 * The configurable_reports_proxy class.
 *
 * This class is the Clearview plugin's configurable reports proxy class.
 */
class configurable_reports_proxy extends \block_configurable_reports {
    /** var Contains the course id. */
    protected $courseid = 0;

    /**
     * Gets the courseid property.
     *
     * @return int
     */
    public function get_courseid(): int {
        return $this->courseid;
    }

    /**
     * Sets the courseid property.
     *
     * @param int $courseid
     */
    public function set_courseid(int $courseid): void {
        $this->courseid = $courseid;
    }

    /**
     * Gets the contents of the block (course view)
     *
     * @return object An object with the contents
     **/
    public function get_content() {
        global $DB, $USER, $CFG;

        if ($this->courseid === 0) {
            $this->content = new \stdClass;
            $this->content->footer = '';
            $this->content->icons = [];
        }

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new \stdClass;
        $this->content->footer = '';
        $this->content->icons = [];

        if (!isloggedin()) {
            return $this->content;
        }

        require_once($CFG->dirroot."/blocks/configurable_reports/locallib.php");

        $course = $DB->get_record('course', array('id' => $this->courseid));

        if (!$course) {
            print_error('coursedoesnotexists');
        }

        if ($course->id == SITEID) {
            $context = \context_system::instance();
        } else {
            $context = \context_course::instance($course->id);
        }

        // Site (Shared) reports.
        $reports = $DB->get_records('block_configurable_reports', array('global' => 1), 'name ASC');

        if ($reports) {
            foreach ($reports as $report) {
                if ($report->visible && cr_check_report_permissions($report, $USER->id, $context)) {
                    $rname = format_string($report->name);
                    $params = ['id' => $report->id, 'courseid' => $course->id];
                    $url = new \moodle_url('/blocks/configurable_reports/viewreport.php', $params);
                    $attrs = ['alt' => $rname];
                    $this->content->items['global'][] = \html_writer::link($url, $rname, $attrs);
                }
            }
        }

        // Course reports.
        if (!property_exists($this, 'config')
            or !isset($this->config->displayreportslist)
            or $this->config->displayreportslist) {
            $reports = $DB->get_records('block_configurable_reports', array('courseid' => $course->id), 'name ASC');

            if ($reports) {
                foreach ($reports as $report) {
                    if (!$report->global && $report->visible && cr_check_report_permissions($report, $USER->id, $context)) {
                        $rname = format_string($report->name);
                        $params = ['id' => $report->id, 'courseid' => $course->id];
                        $url = new \moodle_url('/blocks/configurable_reports/viewreport.php', $params);
                        $attrs = ['alt' => $rname];
                        $this->content->items['course'][] = \html_writer::link($url, $rname, $attrs);
                    }
                }
                if (!empty($this->content->items)) {
                    $this->content->items[] = '========';
                }
            }
        }

        if (has_capability('block/configurable_reports:managereports', $context)
            || has_capability('block/configurable_reports:manageownreports', $context)) {
            $url = new \moodle_url('/blocks/configurable_reports/managereport.php', ['courseid' => $course->id]);
            $linktext = get_string('managereports', 'block_configurable_reports');
            $this->content->items['manage'][] = \html_writer::link($url, $linktext);
        }

        return $this->content;
    }
}
