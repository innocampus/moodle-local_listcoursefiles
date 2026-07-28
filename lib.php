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
 * Legacy plugin functions library.
 *
 * @package   local_listcoursefiles
 * @copyright 2016 Martin Gauk (@innoCampus, TU Berlin)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\context;
use core\context\course as context_course;
use core\context\module as context_module;
use core\exception\moodle_exception;
use core\output\pix_icon;
// TODO: Add `use core\navigation\settings_navigation;` once Moodle 5.0 support is dropped.

/**
 * Adds the course files overview page link to the course administration.
 *
 * @see https://moodledev.io/docs/plugintypes/local#adding-an-element-to-the-settings-menu Moodle docs
 *
 * @param settings_navigation $nav Settings navigation object for the current page.
 * @param context $context Context of the current page.
 * @throws moodle_exception
 *
 * {@noinspection PhpUnused}
 */
function local_listcoursefiles_extend_settings_navigation(settings_navigation $nav, context $context): void {
    if (
        ($context instanceof context_course || $context instanceof context_module)
        && has_capability('local/listcoursefiles:view', $context)
        && $course = $nav->get('courseadmin')
    ) {
        $text = get_string('nav_link_course_files', 'local_listcoursefiles');
        $url = new moodle_url('/local/listcoursefiles/index.php', ['courseid' => $context->get_course_context()->instanceid]);
        $course->add($text, $url, icon: new pix_icon('i/report', ''));
    }
}
