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

use moodle_url;

/**
 * Represents a file uploaded in a book module context.
 *
 * @package   local_listcoursefiles
 * @author    Jeremy FitzPatrick
 * @copyright 2022 Te Wānanga o Aotearoa
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_book extends mod {
    #[\Override]
    protected function get_download_url(): moodle_url|null {
        if ($this->filearea == 'chapter') {
            return $this->get_standard_download_url();
        }
        return parent::get_download_url();
    }

    #[\Override]
    protected function get_edit_url(): moodle_url|null {
        global $DB;
        if ($this->filearea === 'chapter') {
            $instanceid = $DB->get_field('context', 'instanceid', ['id' => $this->contextid]);
            return new moodle_url('/mod/book/edit.php', ['cmid' => $instanceid, 'id' => $this->itemid]);
        }
        return parent::get_edit_url();
    }

    #[\Override]
    protected function get_embedding_context(): string|false {
        global $DB;
        if ($this->filearea === 'chapter') {
            return $DB->get_field('book_chapters', 'content', ['id' => $this->itemid]);
        }
        // Parent implementation covers the `intro` file area.
        return parent::get_embedding_context();
    }
}
