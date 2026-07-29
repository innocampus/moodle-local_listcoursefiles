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

use core\exception\moodle_exception;
use dml_exception;
use moodle_url;

/**
 * Represents a file uploaded in a label module context.
 *
 * @package   local_listcoursefiles
 * @author    Jeremy FitzPatrick
 * @copyright 2022 Te Wānanga o Aotearoa
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_label extends mod {
    #[\Override]
    protected function get_download_url(): moodle_url {
        return $this->get_standard_download_url(insertitemid: false);
    }

    /**
     * {@inheritDoc}
     *
     * @throws dml_exception
     * @throws moodle_exception
     */
    #[\Override]
    protected function get_component_url(): moodle_url {
        global $DB;
        $sql = "SELECT cm.course, cm.section
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                 WHERE ctx.id = :contextid";
        $mod = $DB->get_record_sql($sql, ['contextid' => $this->contextid]);
        return new moodle_url('/course/view.php', ['id' => $mod->course, 'sectionid' => $mod->section]);
    }
}
