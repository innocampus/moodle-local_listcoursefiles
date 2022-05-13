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
 * Class mod_glossary
 * @package local_listcoursefiles
 * @author Jeremy FitzPatrick
 * @copyright 2022 Te Wānanga o Aotearoa
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_glossary extends course_file {
    /**
     * Try to get the download url for a file.
     *
     * @param object $file
     * @return null|\moodle_url
     */
    public function get_file_download_url($file) {
        if ($file->filearea === 'entry' || $file->filearea === 'attachment') {
            return $this->get_standard_file_download_url($file);
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
        // File areas = intro, chapter.
        global $DB;
        if ($file->filearea === 'attachment') {
            return true;
        } else if ($file->filearea === 'entry') {
            $entry = $DB->get_record('glossary_entries', ['id' => $file->itemid]);
            $isused = $this->is_embedded_file_used($entry, 'definition', $file->filename);
            return $isused;
        } else {
            return parent::is_file_used($file);
        }
    }
}
