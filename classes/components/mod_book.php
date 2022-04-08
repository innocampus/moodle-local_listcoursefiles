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
 * Class mod_book
 * @package local_listcoursefiles
 */
class mod_book extends course_file {
    /**
     * Try to get the download url for a file.
     *
     * @param array $file
     * @return null|\moodle_url
     */
    public function get_file_download_url($file) {
        if ($file->filearea == 'chapter') {
            return new \moodle_url('/pluginfile.php/' . $file->contextid . '/' . $file->component . '/' .
                $file->filearea . '/' . $file->itemid . '/' . $file->filepath . $file->filename);
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
        if ($file->filearea === 'chapter') { // Just checking description for now.
            $ctx = $DB->get_record('context', ['id' => $file->contextid]);
            $url = new \moodle_url('/mod/book/edit.php', ['cmid' => $ctx->instanceid, 'id' => $file->itemid]);
        } else {
            $url = parent::get_edit_url($file);
        }

        return $url->out(false);
    }

    /**
     * Checks if embedded files have been used
     *
     * @param object $file
     * @return bool
     */
    public function is_file_used($file) {
        // File areas = intro, chapter.
        global $DB;
        if ($file->filearea === 'chapter') {
            $chapter = $DB->get_record('book_chapters', ['id' => $file->itemid]);
            $isused = $this->is_embedded_file_used($chapter, 'content', $file->filename);
            return $isused;
        } else {
            return parent::is_file_used($file);
        }
    }
}
