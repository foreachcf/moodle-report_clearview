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
 * Plugin administration pages are defined here.
 *
 * @package     clearview
 * @category    admin
 * @copyright   2024 Andrew Caya <andrewscaya@yahoo.ca>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $ADMIN, $DB;

if (!defined('NUMBER_OF_TENANCY_MANAGERS')) {
    define('NUMBER_OF_TENANCY_MANAGERS', 1);
}

if ($hassiteconfig) {
    // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_requiredtext('report_clearview/numberoftenancymanagers', get_string('tmnumber', 'report_clearview'), get_string('tmnumberdesc', 'report_clearview'), NUMBER_OF_TENANCY_MANAGERS, PARAM_INT));

        $roleobjects = $DB->get_records('role', null, null, '*');

        $allroles = [];

        foreach ($roleobjects as $key => $singleroleobject) {
            $allroles[$key] = ucfirst($singleroleobject->shortname);
        }

        try {
            $numberoftenancymanagers = get_config('report_clearview', 'numberoftenancymanagers');
        } catch (\Exception $e) {
            $numberoftenancymanagers = NUMBER_OF_TENANCY_MANAGERS;
        }

        for ($i = 1; $i <= $numberoftenancymanagers; $i++) {
            $settings->add(new admin_setting_heading('report_clearview/tmheading' . $i, get_string('tmheading', 'report_clearview') . ' ' . $i, get_string('tmheadingdesc', 'report_clearview')));
            $settings->add(new admin_setting_configselect_autocomplete('report_clearview/tmauthorityrole' . $i, get_string('tmauthorityrole', 'report_clearview') . ' ' . $i, get_string('tmauthorityroledesc', 'report_clearview'), 1, $allroles));
            $settings->add(new admin_setting_configmultiselect('report_clearview/tmmappedroles' . $i, get_string('tmmappedroles', 'report_clearview') . ' ' . $i, get_string('tmmappedrolesdesc', 'report_clearview'), [4, 5], $allroles));
        }
    }
}
