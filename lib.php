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
 * API of local listcoursefiles.
 *
 * @package   local_listcoursefiles
 * @copyright 2016 Martin Gauk (@innoCampus, TU Berlin)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds link to Course administration
 *
 * @param settings_navigation $nav
 * @param context $context
 * @throws coding_exception
 * @throws moodle_exception
 *
 * {@noinspection PhpUnused}
 */
function local_listcoursefiles_extend_settings_navigation(settings_navigation $nav, context $context): void {
    if ($context && ($context instanceof context_course || $context instanceof context_module)) {
        if (has_capability('local/listcoursefiles:view', $context) && $course = $nav->get('courseadmin')) {
            $url = new moodle_url(
                '/local/listcoursefiles/index.php',
                ['courseid' => $context->get_course_context()->instanceid],
            );
            $text = get_string('linkname', 'local_listcoursefiles');
            $course->add($text, $url, navigation_node::TYPE_CUSTOM, null, null, new pix_icon('i/report', ''));
        }
    }
}
