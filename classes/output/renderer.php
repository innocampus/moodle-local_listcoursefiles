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
 * Output rendering for the plugin.
 *
 * @package     local_listcoursefiles
 * @copyright   2022 Martin Gauk (@innoCampus, TU Berlin)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_listcoursefiles\output;

use moodle_url;

/**
 * Implements the plugin renderer
 *
 * @copyright 2022 Martin Gauk (@innoCampus, TU Berlin)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    /**
     * Render overview page.
     *
     * @param moodle_url $url
     * @param \local_listcoursefiles\course_files $files
     * @param int $page
     * @param int $limit
     * @param array $filelist
     * @param bool $changelicenseallowed
     * @param bool $downloadallowed
     * @return string
     * @throws \moodle_exception
     */
    public function overview_page(moodle_url $url, \local_listcoursefiles\course_files $files, int $page, int $limit,
            array $filelist, bool $changelicenseallowed, bool $downloadallowed) {
        $tpldata = new \stdClass();
        $tpldata->course_selection_html = $this->get_course_selection($url, $files->get_course_id());
        $tpldata->component_selection_html = $this->get_component_selection($url, $files->get_components(),
            $files->get_filter_component());
        $tpldata->file_type_selection_html = $this->get_file_type_selection($url, $files->get_filter_file_type());
        $tpldata->paging_bar_html = $this->output->paging_bar($files->get_file_list_total_size(), $page , $limit, $url, 'page');
        $tpldata->url = $url;
        $tpldata->sesskey = sesskey();
        $tpldata->files = array();
        $tpldata->files_exist = count($filelist) > 0;
        $tpldata->change_license_allowed = $changelicenseallowed;
        $tpldata->download_allowed = $downloadallowed;
        $licenses = \local_listcoursefiles\licences::get_available_licenses();
        $tpldata->license_select_html = \html_writer::select($licenses, 'license');

        foreach ($filelist as $file) {
            $classname = '\local_listcoursefiles\components\\' . $file->component;
            if (class_exists($classname)) {
                $tplfile = new $classname($file);
            } else {
                $tplfile = new \local_listcoursefiles\course_file($file);
            }
            $tpldata->files[] = $tplfile;
        }

        return $this->render_from_template('local_listcoursefiles/view', $tpldata);
    }

    /**
     * Builds the course select drop-down menu HTML snippet.
     *
     * @param moodle_url $url
     * @param int $currentcourseid
     * @return string
     * @throws \coding_exception
     */
    public function get_course_selection(moodle_url $url, int $currentcourseid) {
        $url = clone $url;
        $url->remove_params('courseid', 'page');

        $availcourses = array();
        $allcourses = enrol_get_my_courses();
        foreach ($allcourses as $course) {
            $context = \context_course::instance($course->id, IGNORE_MISSING);
            if (has_capability('local/listcoursefiles:view', $context)) {
                $availcourses[$course->id] = $course->shortname;
            }
        }

        return $this->output->single_select($url, 'courseid', $availcourses, $currentcourseid, null, 'courseselector');
    }

    /**
     * Builds the file component select drop-down menu HTML snippet.
     *
     * @param moodle_url $url
     * @param array $allcomponents
     * @param string $currentcomponent
     * @return string
     */
    public function get_component_selection(moodle_url $url, array $allcomponents, string $currentcomponent) {
        $url = clone $url;
        $url->remove_params('page');

        return $this->output->single_select($url, 'component', $allcomponents, $currentcomponent, null, 'componentselector');
    }

    /**
     * Builds the file type select drop-down menu HTML snippet.
     *
     * @param moodle_url $url
     * @param string $currenttype
     * @return string
     * @throws \coding_exception
     */
    public function get_file_type_selection(moodle_url $url, $currenttype) {
        $url = clone $url;
        $url->remove_params('page');

        return $this->output->single_select($url, 'filetype', \local_listcoursefiles\course_files::get_file_types(),
            $currenttype, null, 'filetypeselector');
    }
}
