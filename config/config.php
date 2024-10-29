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
 * Configures clearview advanced reports.
 *
 * @package     report_clearview
 * @copyright   2024 Andrew Caya <andrewscaya@yahoo.ca>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined("MOODLE_INTERNAL") || die();

/**
 * These are the default settings for the Clearview Reports.
 *
 * Please do not modify this file. For local changes, create a file named
 * "config.local.php" in the same directory as this "config.php" file,
 * and add/replace elements of this default configuration.
 *
 */

global $CFG;

if (!isset($CFG->clearview) || empty($CFG->clearview)) {
    $config = require_once(__DIR__ . '/config.local.php');

    if (!empty($config['reports'])) {
        $CFG->clearview['reports'] = $config['reports'];
    } else {
        $CFG->clearview['reports'] = [];
    }

}
