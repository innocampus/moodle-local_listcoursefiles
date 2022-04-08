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
 */
class mod_glossary extends course_file {
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
