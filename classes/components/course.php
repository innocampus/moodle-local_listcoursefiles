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

namespace local_listcoursefiles\components;

use local_listcoursefiles\course_file;

/**
 * Class course
 * @package local_listcoursefiles
 */
class course extends course_file {
    /**
     * Try to get the download url for a file.
     *
     * @param array $file
     * @return null|\moodle_url
     */
    public function get_file_download_url($file) {
        switch ($file->filearea) {
            case 'section':
                return new \moodle_url('/pluginfile.php/' . $file->contextid . '/' . $file->component . '/' .
                    $file->filearea . '/' . $file->itemid . $file->filepath . $file->filename);
            case 'legacy':
                return new \moodle_url('/file.php/' . $this->courseid . $file->filepath . $file->filename);
            case 'overviewfiles':
                return new \moodle_url('/pluginfile.php/' . $file->contextid . '/' . $file->component . '/' .
                    $file->filearea . '/' . $file->filepath . $file->filename);
            default :
                return parent::get_file_download_url($file);
        }

    }

    /**
     * Try to get the url for the component (module or course).
     *
     * @param object $file
     * @return null|\moodle_url
     * @throws moodle_exception
     */
    public function get_component_url($file) {
        if ($file->component === 'contentbank') {
            return new \moodle_url('/contentbank/index.php', ['contextid' => $file->contextid]);
        } else if ($file->filearea === 'section') {
            return new \moodle_url('/course/view.php', array(
                'id' => $this->courseid,
                'sectionid' => $file->itemid
            ));
        } else {
            return new \moodle_url('/course/info.php', array(
                'id' => $this->courseid
            ));
        }
    }

    /**
     * @param object $file
     * @return \moodle_url
     * @throws \moodle_exception
     */
    public function get_edit_url($file) {
        if ($file->filearea === 'section') {
            $url = new \moodle_url('/course/editsection.php?', ['id' => $file->itemid]);
        } else if ($file->filearea === 'overviewfiles' || $file->filearea === 'summary') {
            $url = new \moodle_url('/course/edit.php?', ['id' => $this->courseid]);
        }

        return $url;
    }

    /**
     * Checks if embedded files have been used
     *
     * @param object $file
     * @return bool
     * @throws \dml_exception
     */
    public function is_file_used($file) {
        global $DB;

        if ($file->filearea === 'section') {
            $section = $DB->get_record('course_sections', ['id' => $file->itemid]);
            $isused = $this->is_embedded_file_used($section, 'summary', $file->filename);
        } else if ($file->filearea === 'overviewfiles') {
            $isused = true;
        } else if ($file->filearea === 'summary') {
            $course = $DB->get_record('course', ['id' => $this->courseid]);
            $isused = $this->is_embedded_file_used($course, 'summary', $file->filename);
        }

        return $isused;
    }
}
