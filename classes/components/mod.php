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
use core_component;
use dml_exception;
use local_listcoursefiles\course_file;
use moodle_url;

/**
 * Represents a file uploaded in a generic module context.
 *
 * @package   local_listcoursefiles
 * @copyright 2026 Daniel Fainberg (@innoCampus, TU Berlin)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod extends course_file {
    /**
     * {@inheritDoc}
     *
     * @throws dml_exception
     */
    #[\Override]
    protected function get_embedding_context(): string|false {
        global $DB;
        $modname = substr($this->component, 4);
        if ($this->filearea === 'intro' && array_key_exists($modname, core_component::get_plugin_list('mod'))) {
            $sql = "SELECT m.intro
                          FROM {context} ctx
                          JOIN {course_modules} cm ON cm.id = ctx.instanceid
                          JOIN {{$modname}} m ON m.id = cm.instance
                         WHERE ctx.id = :contextid";
            return $DB->get_field_sql($sql, ['contextid' => $this->contextid]);
        }
        return parent::get_embedding_context();
    }

    /**
     * {@inheritDoc}
     *
     * @throws dml_exception
     * @throws moodle_exception
     */
    #[\Override]
    protected function get_edit_url(): moodle_url|null {
        if ($this->filearea === 'intro') {
            return $this->get_edit_url_from_context();
        }
        return parent::get_edit_url();
    }

    /**
     * Returns the URL for editing the module derived from the file's context ID.
     *
     * @return moodle_url Appropriate `/course/modedit.php` URL.
     * @throws dml_exception
     * @throws moodle_exception
     */
    final protected function get_edit_url_from_context(): moodle_url {
        global $DB;
        $sql = "SELECT cm.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                 WHERE ctx.id = :contextid";
        $id = $DB->get_field_sql($sql, ['contextid' => $this->contextid]);
        return new moodle_url('/course/modedit.php', ['update' => $id]);
    }
}
