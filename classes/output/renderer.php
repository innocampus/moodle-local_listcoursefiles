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
 * Definition of the {@link \local_listcoursefiles\output\renderer} class.
 *
 * @package   local_listcoursefiles
 * @copyright 2022 Martin Gauk (@innoCampus, TU Berlin)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_listcoursefiles\output;

use context_course;
use core\exception\coding_exception;
use core\exception\moodle_exception;
use dml_exception;
use html_writer;
use moodle_url;
use local_listcoursefiles\course_file;
use local_listcoursefiles\course_files;
use local_listcoursefiles\licences;
use plugin_renderer_base;
use stdClass;

/**
 * Provides the method to render the course files overview page.
 *
 * @copyright 2022 Martin Gauk (@innoCampus, TU Berlin)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {
    /**
     * Returns the rendered overview page.
     *
     * @param moodle_url $url Form action URL.
     * @param course_files $files Files to display.
     * @param int $page Current page number.
     * @return string Rendered HTML.
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function overview_page(moodle_url $url, course_files $files, int $page): string {
        $filelist = array_values(
            array_map(fn (stdClass $file): course_file => course_file::create($file), $files->fetch())
        );
        $context = [
            'course_selection_html' => $this->get_course_selection($url, $files->courseid),
            'component_selection_html' => $this->get_component_selection($url, $files->get_components(), $files->component),
            'file_type_selection_html' => $this->get_file_type_selection($url, $files->filetype),
            'paging_bar_html' => $this->output->paging_bar($files->count(), $page, $files->limit, $url),
            'url' => $url,
            'sesskey' => sesskey(),
            'files' => $filelist,
            'files_exist' => count($filelist) > 0,
            'change_license_allowed' => has_capability('local/listcoursefiles:change_license', $files->context),
            'download_allowed' => has_capability('local/listcoursefiles:download', $files->context),
            'license_select_html' => html_writer::select(licences::get_available_licenses(), 'license'),
        ];
        return $this->render_from_template('local_listcoursefiles/view', $context);
    }

    /**
     * Builds an HTML snippet for the course selection drop-down menu.
     *
     * @param moodle_url $url Form action URL.
     * @param int $currentcourseid Currently selected course ID.
     * @return string HTML snippet.
     * @throws coding_exception
     */
    private function get_course_selection(moodle_url $url, int $currentcourseid): string {
        $url = clone $url;
        $url->remove_params('courseid', 'page');
        return $this->output->single_select(
            url: $url,
            name: 'courseid',
            options: array_column(
                array: array_filter(enrol_get_my_courses(), [self::class, 'can_view_course_files']),
                column_key: 'shortname',
                index_key: 'id',
            ),
            selected: $currentcourseid,
            nothing: '',
            formid: 'courseselector',
        );
    }

    /**
     * Checks whether the current user is permitted to view the files list for the specified course.
     *
     * @param stdClass $course Course object.
     * @return bool `true`, if the course files page can be viewed, `false` otherwise.
     * @throws coding_exception
     */
    private static function can_view_course_files(stdClass $course): bool {
        $context = context_course::instance($course->id, IGNORE_MISSING);
        return $context && has_capability('local/listcoursefiles:view', $context);
    }

    /**
     * Builds an HTML snippet for the component selection drop-down menu.
     *
     * @param moodle_url $url Form action URL.
     * @param array $allcomponents All available components.
     * @param string $currentcomponent Currently selected component.
     * @return string HTML snippet.
     */
    private function get_component_selection(moodle_url $url, array $allcomponents, string $currentcomponent): string {
        $url = clone $url;
        $url->remove_params('page');
        return $this->output->single_select(
            url: $url,
            name: 'component',
            options: $allcomponents,
            selected: $currentcomponent,
            nothing: '',
            formid: 'componentselector',
        );
    }

    /**
     * Builds an HTML snippet for the file type selection drop-down menu.
     *
     * @param moodle_url $url Form action URL.
     * @param string $currenttype Currently selected file type.
     * @return string HTML snippet.
     * @throws coding_exception
     */
    private function get_file_type_selection(moodle_url $url, string $currenttype): string {
        $url = clone $url;
        $url->remove_params('page');
        return $this->output->single_select(
            url: $url,
            name: 'filetype',
            options: course_files::get_file_types_display_names(),
            selected: $currenttype,
            nothing: '',
            formid: 'filetypeselector',
        );
    }
}
