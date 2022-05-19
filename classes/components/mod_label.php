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
 * Class mod_label
 * @package local_listcoursefiles
 * @author Jeremy FitzPatrick
 * @copyright 2022 Te Wānanga o Aotearoa
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_label extends course_file {
    /**
     * Try to get the download url for a file.
     *
     * @return null|\moodle_url
     * @throws \moodle_exception
     */
    protected function get_file_download_url() : ?\moodle_url {
        return $this->get_standard_file_download_url(false);
    }

    /**
     * Try to get the url for the component (module or course).
     *
     * @return null|\moodle_url
     * @throws \moodle_exception
     */
    protected function get_component_url() : ?\moodle_url {
        global $DB;
        $sql = "SELECT cm.*
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                 WHERE ctx.id = ?";
        $mod = $DB->get_record_sql($sql, [$this->file->contextid]);
        return new \moodle_url('/course/view.php', ['id' => $mod->course, 'sectionid' => $mod->section]);
    }
}
