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
 * @author Jeremy FitzPatrick
 * @copyright 2022 Te Wānanga o Aotearoa
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course extends course_file {
    /**
     * Try to get the download url for a file.
     *
     * @param object $file
     * @return null|\moodle_url
     */
    public function get_file_download_url($file) {
        switch ($file->filearea) {
            case 'section':
                return $this->get_standard_file_download_url($file);
            case 'legacy':
                return new \moodle_url('/file.php/' . $this->courseid . $file->filepath . $file->filename);
            case 'overviewfiles':
                return $this->get_standard_file_download_url($file, false);
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
     * Creates the URL for the editor where the file is added
     *
     * @param object $file
     * @return \moodle_url|null
     * @throws \moodle_exception
     */
    public function get_edit_url($file) {
        if ($file->filearea === 'section') {
            return new \moodle_url('/course/editsection.php?', ['id' => $file->itemid]);
        } else if ($file->filearea === 'overviewfiles' || $file->filearea === 'summary') {
            return new \moodle_url('/course/edit.php?', ['id' => $this->courseid]);
        }

        return parent::get_edit_url($file);
    }

    /**
     * Checks if embedded files have been used
     *
     * @param object $file
     * @return bool|null
     * @throws \dml_exception
     */
    public function is_file_used($file) {
        global $DB;

        if ($file->filearea === 'section') {
            $section = $DB->get_record('course_sections', ['id' => $file->itemid]);
            return $this->is_embedded_file_used($section, 'summary', $file->filename);
        } else if ($file->filearea === 'overviewfiles') {
            return true;
        } else if ($file->filearea === 'summary') {
            $course = $DB->get_record('course', ['id' => $this->courseid]);
            return $this->is_embedded_file_used($course, 'summary', $file->filename);
        }

        return parent::is_file_used($file);
    }
}
