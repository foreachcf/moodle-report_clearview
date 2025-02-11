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
 * Refresh the category cache scheduled task.
 *
 * @package     report_clearview
 * @copyright   2024 Andrew Caya <andrewscaya@yahoo.ca>
 * @author      Andrew Caya
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_clearview\task;

use core\task\scheduled_task;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/report/clearview/lib.php');
require_once($CFG->dirroot . '/report/clearview/config/config.php');

/**
 * Scheduled task to refresh the category data in the plugin's cache.
 */
class refreshcategorycache extends scheduled_task {
    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskrefreshcategorycache', 'report_clearview');
    }

    /**
     * Print debugging information using mtrace.
     *
     * @param string $msg
     */
    protected function mtrace($msg) {
        mtrace('...... ' . $msg);
    }

    /**
     * Do the job.
     */
    public function execute() {
        global $CFG;

        $this->mtrace('Starting refresh of category data cache.');

        raise_memory_limit(MEMORY_HUGE);

        // Do not time out.
        @set_time_limit(0);

        $categorydatacacherefreshflag = true;

        $categories = \core_course_category::make_categories_list();

        $coursecatcache = \cache::make('report_clearview', 'categorydatacache');
        $coursecatcache->purge();

        if (!empty($categories)) {
            foreach ($categories as $categoryid => $categoryfqn) {
                $this->mtrace('Refreshing cache of category with id ' . $categoryid);

                $categorydata = build_category_data($categoryid, $categoryfqn);
                $categorydatapayload = serialize($categorydata);

                $basecachekey = 'report_clearview_catcache_' . $categoryid;
                $coursecatcache->set($basecachekey, $categorydatapayload);
            }
        }

        $this->mtrace('Refresh of category data cache finished.');

        $tenantwwwroot = $CFG->wwwroot;

        foreach ($CFG->clearview['reports'] as $reportkey => $report) {
            $classname = '\\report_clearview\\reports\\' . $report['classname'];

            if ($classname::CONTEXTLEVEL === CONTEXT_SYSTEM
                && ($classname::WWWROOT === '*'
                    || preg_match('/.*' . $classname::WWWROOT . '.*/', $tenantwwwroot))
            ) {
                $this->mtrace('Refreshing cache of advanced report with id ' . $reportkey);

                $advreport = new $classname($reportkey, $CFG->clearview['reports'][$reportkey]['headers'], SITEID);

                $advreportdatapayload = serialize($advreport->get_report_data());
                $basecachekey = 'report_clearview_catcache_advreport_' . $reportkey;
                $coursecatcache->set($basecachekey, $advreportdatapayload);
            }
        }

        $this->mtrace('Refresh of advanced report data cache finished.');

        return $categorydatacacherefreshflag;
    }
}
