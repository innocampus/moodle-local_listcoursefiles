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
 * Class mod_h5pactivity
 *
 * @package   local_listcoursefiles
 * @author    Jeremy FitzPatrick
 * @copyright 2022 Te Wānanga o Aotearoa
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_h5pactivity extends course_file {
    /**
     * Creates the URL for the editor where the file is added
     *
     * @return moodle_url|null
     * @throws moodle_exception
     */
    protected function get_edit_url(): ?moodle_url {
        global $DB;
        if ($this->file->filearea === 'package') {
            $sql = "SELECT cm.*
                      FROM {context} ctx
                      JOIN {course_modules} cm ON cm.id = ctx.instanceid
                     WHERE ctx.id = ?";
            $mod = $DB->get_record_sql($sql, [$this->file->contextid]);
            return new moodle_url('/course/modedit.php?', ['update' => $mod->id]);
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
        // File areas = intro, package.
        if ($this->file->filearea === 'package') {
            return true;
        }
        return parent::is_file_used();
    }
}
