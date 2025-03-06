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

use dml_exception;
use local_listcoursefiles\course_file;
use moodle_exception;
use moodle_url;

/**
 * Class course
 *
 * @package   local_listcoursefiles
 * @author    Jeremy FitzPatrick
 * @copyright 2022 Te Wānanga o Aotearoa
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course extends course_file {
    /**
     * Try to get the download url for a file.
     *
     * @return moodle_url|null
     * @throws moodle_exception
     */
    protected function get_file_download_url(): ?moodle_url {
        switch ($this->file->filearea) {
            case 'section':
                return $this->get_standard_file_download_url();
            case 'legacy':
                return new moodle_url('/file.php/' . $this->courseid . $this->file->filepath . $this->file->filename);
            case 'overviewfiles':
                return $this->get_standard_file_download_url(false);
            default:
                return parent::get_file_download_url();
        }
    }

    /**
     * Try to get the url for the component (module or course).
     *
     * @return moodle_url
     * @throws moodle_exception
     */
    protected function get_component_url(): moodle_url {
        if ($this->file->component === 'contentbank') {
            return new moodle_url('/contentbank/index.php', ['contextid' => $this->file->contextid]);
        }
        if ($this->file->filearea === 'section') {
            return new moodle_url('/course/view.php', ['id' => $this->courseid, 'sectionid' => $this->file->itemid]);
        }
        return new moodle_url('/course/info.php', ['id' => $this->courseid]);
    }

    /**
     * Creates the URL for the editor where the file is added
     *
     * @return moodle_url|null
     * @throws moodle_exception
     */
    protected function get_edit_url(): ?moodle_url {
        if ($this->file->filearea === 'section') {
            return new moodle_url('/course/editsection.php?', ['id' => $this->file->itemid]);
        }
        if ($this->file->filearea === 'overviewfiles' || $this->file->filearea === 'summary') {
            return new moodle_url('/course/edit.php?', ['id' => $this->courseid]);
        }
        return parent::get_edit_url();
    }

    /**
     * Checks if embedded files have been used
     *
     * @return bool|null
     * @throws dml_exception
     */
    protected function is_file_used(): ?bool {
        global $DB;
        switch ($this->file->filearea) {
            case 'section':
                $section = $DB->get_record('course_sections', ['id' => $this->file->itemid]);
                return $this->is_embedded_file_used($section, 'summary', $this->file->filename);
            case 'overviewfiles':
                return true;
            case 'summary':
                $course = $DB->get_record('course', ['id' => $this->courseid]);
                return $this->is_embedded_file_used($course, 'summary', $this->file->filename);
            default:
                return parent::is_file_used();
        }
    }
}
