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
 * Configures the Clearview Advanced Reports.
 *
 * @package     report_clearview
 * @copyright   2024 Andrew Caya <andrewscaya@yahoo.ca>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined("MOODLE_INTERNAL") || die();

/**
 * These are the default local settings for the Clearview Reports.
 *
 */
return [
    'reports' => [
        1 => [
            'title' => [
                'en' => 'Outstanding Assignment Report',
                'fr' => 'Rapport de devoirs en retard (non remis)',
            ],
            'classname' => 'advreport_outstanding_assignments',
            'headers'=> [
                'en' => ['ID', 'Lastname', 'Firstname', 'ID Number', 'Course Title', 'Assignment', 'Grading Date'],
                'fr' => ['ID', 'Nom', 'Prénom', 'Matricule', 'Titre du cours', 'Devoir', 'Date de remise des notes'],
            ],
        ],
    ],
];
