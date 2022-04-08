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
 * Class contentbank
 * @package local_listcoursefiles
 */
class contentbank extends course_file {
    /**
     * Try to get the download url for a file.
     *
     * @param array $file
     * @return null|\moodle_url
     */
    public function get_file_download_url($file) {
        return new \moodle_url('/pluginfile.php/' . $file->contextid . '/' . $file->component . '/' .
            $file->filearea . '/' . $file->itemid . $file->filepath . $file->filename);
    }

    /**
     * Try to get the url for the component (module or course).
     *
     * @param object $file
     * @return null|\moodle_url
     * @throws moodle_exception
     */
    public function get_component_url($file) {
        return new \moodle_url('/contentbank/index.php', ['contextid' => $file->contextid]);
    }

    /**
     * Not checking content bank
     *
     * @param object file
     * @return null
     */
    public function is_file_used($file) {
        return null;
    }
}
