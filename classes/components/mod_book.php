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
 * @author Jeremy FitzPatrick
 * @copyright 2022 Te Wānanga o Aotearoa
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_book extends course_file {
    /**
     * Try to get the download url for a file.
     *
     * @return null|\moodle_url
     * @throws \moodle_exception
     */
    protected function get_file_download_url() : ?\moodle_url {
        if ($this->file->filearea == 'chapter') {
            return $this->get_standard_file_download_url();
        }
        return parent::get_file_download_url();
    }

    /**
     * Creates the URL for the editor where the file is added
     *
     * @return \moodle_url|null
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    protected function get_edit_url() : ?\moodle_url {
        global $DB;
        if ($this->file->filearea === 'chapter') { // Just checking description for now.
            $ctx = $DB->get_record('context', ['id' => $this->file->contextid]);
            return new \moodle_url('/mod/book/edit.php', ['cmid' => $ctx->instanceid, 'id' => $this->file->itemid]);
        }
        return parent::get_edit_url();
    }

    /**
     * Checks if embedded files have been used
     *
     * @return bool|null
     * @throws \dml_exception
     */
    protected function is_file_used() : ?bool {
        // File areas = intro, chapter.
        global $DB;
        if ($this->file->filearea === 'chapter') {
            $chapter = $DB->get_record('book_chapters', ['id' => $this->file->itemid]);
            return $this->is_embedded_file_used($chapter, 'content', $this->file->filename);
        }
        return parent::is_file_used();
    }
}
