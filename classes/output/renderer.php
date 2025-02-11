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
 * Renderer class for report_clearview.
 *
 * @package     report_clearview
 * @copyright   2024 Andrew Caya <andrewscaya@yahoo.ca>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_clearview\output;

defined('MOODLE_INTERNAL') || die;

use plugin_renderer_base;
use \report_clearview\reports\advanced_report;

/**
 * Renderer class for clearview report.
 *
 * This class is the Clearview plugin's renderer class.
 */
class renderer extends plugin_renderer_base {

    /**
     * Render the report.
     *
     * @param string $template
     * @param array $data
     *
     * @return string HTML content of the page
     */
    public function render_report($template, $data) {
        return parent::render_from_template($template, $data);
    }

    /**
     * Render the advanced report.
     *
     * @param advanced_report $report
     *
     * @return string HTML content of the page
     */
    public function render_advanced_report(advanced_report $report) {
        return parent::render_from_template('report_clearview/advreports', $report->export_for_template());
    }
}
