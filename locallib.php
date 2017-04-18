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
 * Internal API of local listcoursefiles.
 *
 * @package    local_listcoursefiles
 * @copyright  2016 Martin Gauk (@innoCampus, TU Berlin)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Maximum number of files per page.
 * @var int
 */
define('LOCAL_LISTCOURSEFILES_MAX_FILES', 500);

function local_listcoursefiles_get_component_translation($name) {
    $translated = $name;
    if (get_string_manager()->string_exists('pluginname', $name)) {
        $translated = get_string('pluginname', $name);
    } else if (get_string_manager()->string_exists($name, '')) {
        $translated = get_string($name, '');
    }
    return $translated;
}

function local_listcoursefiles_get_course_selection(moodle_url $url, $currentcourseid) {
    global $OUTPUT;

    $url = clone $url;
    $url->remove_params('courseid', 'page');

    $availcourses = array();
    $allcourses = enrol_get_my_courses();
    foreach ($allcourses as $course) {
        $context = context_course::instance($course->id, IGNORE_MISSING);
        if (has_capability('local/listcoursefiles:view', $context)) {
            $availcourses[$course->id] = $course->shortname;
        }
    }

    return $OUTPUT->single_select($url, 'courseid', $availcourses, $currentcourseid, null, 'courseselector');
}

function local_listcoursefiles_get_component_selection(moodle_url $url, $allcomponents, $currentcomponent) {
    global $OUTPUT;

    $url = clone $url;
    $url->remove_params('page');

    return $OUTPUT->single_select($url, 'component', $allcomponents, $currentcomponent, null, 'componentselector');
}

function local_listcoursefiles_get_file_type_selection(moodle_url $url, $currenttype) {
    global $OUTPUT;

    $url = clone $url;
    $url->remove_params('page');

    return $OUTPUT->single_select($url, 'filetype', local_listcoursefiles\course_files::get_file_types(),
            $currenttype, null, 'filetypeselector');
}
