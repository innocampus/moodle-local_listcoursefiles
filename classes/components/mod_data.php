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
 * Class mod_data
 * @package local_listcoursefiles
 */
class mod_data extends course_file {
    /**
     * Try to get the download url for a file.
     *
     * @param array $file
     * @return null|\moodle_url
     */
    public function get_file_download_url($file) {
        if ($file->filearea == 'content') {
            return new \moodle_url('/pluginfile.php/' . $file->contextid . '/' . $file->component . '/' .
                $file->filearea . '/' . $file->itemid . $file->filepath . $file->filename);
        } else {
            return parent::get_file_download_url($file);
        }
    }

    /**
     * Checks if embedded files have been used
     *
     * @param object $file
     * @return bool
     */
    public function is_file_used($file) {
        // File areas = intro, content.
        global $DB;
        if ($file->filearea === 'content') {
            $sql = 'SELECT * FROM {data_content} dc
                    JOIN {data_fields} df ON df.id = dc.fieldid
                    WHERE dc.id = ?';
            $data = $DB->get_record_sql($sql, [$file->itemid]);
            if ($data->type !== 'textarea' ||
                false !== strpos($data->content, '@@PLUGINFILE@@/' . rawurlencode($file->filename))) {
                return true;
            } else {
                return false;
            }
        } else {
            return parent::is_file_used($file);
        }
    }
}
