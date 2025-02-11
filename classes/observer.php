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
 * Class that handles any relevant events.
 *
 * @package     report_clearview
 * @copyright   2024 Andrew Caya <andrewscaya@yahoo.ca>
 * @author      Andrew Caya
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_clearview;

defined('MOODLE_INTERNAL') || die();

/**
 * The observer class.
 *
 * This class is this plugin's main subscriber class (pubsub pattern)
 * to the Clearview plugin's main events (Moodle's Event API).
 */
class observer {
    /**
     * Intercepts all wiki events and checks user access.
     *
     * @param \core\event\base $event
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function listen(\core\event\base $event) {
        global $DB;

        if ($event->component !== 'report_clearview') {
            // Nothing to do if the requested module is not a clearview request.
            return;
        }

        $categories = get_current_user_categories();

        $studentroles = get_student_roles();
        $categorydata = [];

        foreach ($categories as $categoryid => $categoryfqn) {
            $courses = [];
            $userids = [];
            $currentstudents = [];

            $categoryobject = get_category_object($categoryid);

            // To get immediate children.
            $children = get_category_children($categoryobject);

            $courses = get_category_courses($categoryobject);

            $courseswithids = get_category_courses_with_ids($categoryobject);

            if (!empty($courseswithids['ids'])) {
                foreach ($courseswithids['ids'] as $courseid) {
                    $context = \context_course::instance($courseid);
                    $courseusers = get_enrolled_users($context, '', 0, 'u.id');
                    $userids = array_merge($userids, array_keys($courseusers));

                    $students = get_role_users($studentroles, $context);

                    foreach ($students as $singlestudent) {
                        $currentstudents[$singlestudent->id] = $singlestudent;
                    }
                }
            }

            $useridsuniq = array_unique($userids);

            sort($useridsuniq);

            ksort($currentstudents);

            $categorydata[$categoryid] = [
                'id' => $categoryid,
                'name' => $categoryfqn,
                'courses' => $courses,
                'users' => $useridsuniq,
                'students' => $currentstudents,
            ];

            $subcategories = get_category_children($categoryobject);

            foreach ($subcategories as $childcategoryobject) {
                $coursestmp = get_category_courses($childcategoryobject);

                if (!empty($coursestmp)) {
                    $courses[$childcategoryobject->id] = $coursestmp;
                }

                $courseids = $childcategoryobject->get_courses(['recursive', 'idonly']);

                if (!empty($courseids)) {
                    foreach ($courseids as $courseid) {
                        $context = \context_course::instance($courseid->id);
                        $courseusers = get_enrolled_users($context, '', 0, 'u.id');
                        $userids = array_merge($userids, array_keys($courseusers));

                        $students = get_role_users($studentroles, $context);

                        foreach ($students as $singlestudent) {
                            $currentstudents[$singlestudent->id] = $singlestudent;
                        }
                    }
                }
            }

            $categorydata[$categoryid]['all_courses'] = $courses;

            $useridsuniq = array_unique($userids);

            sort($useridsuniq);

            $categorydata[$categoryid]['all_users'] = $useridsuniq;

            ksort($currentstudents);

            $categorydata[$categoryid]['all_students'] = $currentstudents;
        }

        var_dump($categorydata);
        exit;

        $context = \context::instance_by_id($event->contextid);
        $usercontext = \context_user::instance($event->userid);
        $modcontext = \context_module::instance($event->contextinstanceid);
        $contextblock = \context_block::instance($event->contextinstanceid);

        $accesscategories = [];

        // Optionally, $event->contextid for injected element from controller.
        if ($context->contextlevel == CONTEXT_COURSECAT) {
            $contextinstanceid = $DB->get_record('context', ['id' => $context->id], 'instanceid', MUST_EXIST);
            $categorycontext = \context_coursecat::instance($contextinstanceid);
            $category = $DB->get_record('course_categories', ['id' => $contextinstanceid], '*', MUST_EXIST);
            $accesscategories[] = $category;
        }

        $course_id = $event->courseid;
        $coursecontext = \context_course::instance($course_id);
        $contextmodule = \context_module::instance($event->contextinstanceid);
        $categorycontext = \context_coursecat::instance($category->id ?: 1);

        $data = $event->get_data();

        $table = $data['objecttable'];
        $tableid = $data['objectid'];

        if (!$wikiinstance = $DB->get_record('local_wikifilter_instances', ['cmid' => $event->contextinstanceid], '*')) {
            // Nothing to do if the requested wiki is not a filtered wiki.
            return;
        }

        // Check user permissions.
        $context = \context_module::instance($event->contextinstanceid);
        $userroles = get_user_roles($context, $event->userid);

        if (empty($userroles)) {
            $admins = get_admins();

            foreach ($admins as $admin) {
                if ($USER->id === $admin->id) {
                    // Admins can access anything.
                    return;
                }
            }
        }

        $accessroles = [];

        foreach ($userroles as $role) {
            $roledefinition = $DB->get_record('role', ['id' => $role->roleid], 'archetype', MUST_EXIST);

            if ($roledefinition->archetype === 'editingteacher' || $roledefinition->archetype === 'manager') {
                // The editing teacher and the manager can access anything.
                return;
            } else {
                $accessroles[] = $role->shortname;
            }
        }

        $data = $event->get_data();

        $wikipagestable = $data['objecttable'];
        $wikipagestableid = $data['objectid'];

        $course_id = $event->courseid;
        //$module = $DB->get_record('modules', ['name' => 'wiki'], 'id', MUST_EXIST);
        //$course_mod = $DB->get_record('course_modules', ['module' => $module->id, 'course' => $course_id], 'id', MUST_EXIST);
        //$cm = get_coursemodule_from_id('wiki', $course_mod->id, $course_id, false, MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_cmid($event->contextinstanceid, 'wiki');
        $wiki = $DB->get_record('wiki', ['id' => $cm->instance], '*', MUST_EXIST);
        $subwiki = $DB->get_record('wiki_subwikis', ['id' => $wiki->id], '*', MUST_EXIST);
        $wikilinksparent = $DB->get_record('wiki_links' , ['subwikiid' => $subwiki->id, 'topageid' => $wikipagestableid], 'frompageid', MUST_EXIST);
        //$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        //$wikipage = $DB->get_record($wikipagestable , ['id' => $wikipagestableid], '*', MUST_EXIST);

        if (\core_tag_tag::is_enabled('core', 'course_modules')) {
            $tags = \core_tag_tag::get_item_tags('mod_wiki', 'wiki_pages', $wikipagestableid);
        } else {
            throw new \Exception(get_string('tags_not_enabled', 'local_wikifilter'));
        }

        if (empty($tags)) {
            // Everyone has access.
            return;
        }

        foreach ($tags as $tag) {
            if (in_array($tag->name, $accessroles)) {
                // User has access.
                return;
            } else {
                redirect(new \moodle_url('/mod/wiki/view.php', array('pageid' => $wikilinksparent->frompageid)), get_string('access_denied', 'local_wikifilter'));
            }
        }
    }
}
