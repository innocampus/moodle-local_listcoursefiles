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
 * Class mod_assign
 * @package local_listcoursefiles
 */
class mod_assign extends course_file {
    /**
     * Try to get the download url for a file.
     *
     * @param array $file
     * @return null|\moodle_url
     */
    public function get_file_download_url($file) {
        if ($file->filearea == 'introattachment') {
            return new \moodle_url('/pluginfile.php/' . $file->contextid . '/' . $file->component . '/' .
                $file->filearea . '/0/' . $file->filepath . $file->filename);
        } else {
            return parent::get_file_download_url($file);
        }

    }

    /**
     * @param object $file
     * @return \moodle_url|string
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_edit_url($file) {
        global $DB;
        $url = '';
        if ($file->filearea === 'introattachment') {
            $sql = 'SELECT cm.* FROM {context} ctx
                        JOIN {course_modules} cm ON cm.id = ctx.instanceid
                        WHERE ctx.id = ?';
            $mod = $DB->get_record_sql($sql, [$file->contextid]);
            $url = new \moodle_url('/course/modedit.php?', ['update' => $mod->id]);
        } else {
            $url = parent::get_edit_url($file);
        }

        return $url;
    }

    /**
     * Checks if embedded files have been used
     *
     * @param object $file
     * @return bool
     */
    public function is_file_used($file) {
        // File areas = intro, introattachment.
        if ($file->filearea === 'introattachment') {
            return true;
        } else {
            return parent::is_file_used($file);
        }
    }
}
