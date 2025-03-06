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
 * Class mod_forum
 *
 * @package   local_listcoursefiles
 * @author    Jeremy FitzPatrick
 * @copyright 2022 Te Wānanga o Aotearoa
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_forum extends course_file {
    /**
     * Try to get the download url for a file.
     *
     * @return moodle_url|null
     * @throws moodle_exception
     */
    protected function get_file_download_url(): ?moodle_url {
        switch ($this->file->filearea) {
            case 'post':
            case 'attachment':
                return $this->get_standard_file_download_url();
            default:
                return parent::get_file_download_url();
        }
    }

    /**
     * Checks if embedded files have been used
     *
     * @return bool|null
     * @throws dml_exception
     */
    protected function is_file_used(): ?bool {
        // File areas = intro, post.
        global $DB;
        switch ($this->file->filearea) {
            case 'post':
                $post = $DB->get_record('forum_posts', ['id' => $this->file->itemid]);
                return $this->is_embedded_file_used($post, 'message', $this->file->filename);
            case 'attachment':
                return true;
            default:
                return parent::is_file_used();
        }
    }
}
