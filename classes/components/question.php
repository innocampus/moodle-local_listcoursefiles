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
use moodle_url;

/**
 * Represents a file uploaded in a generic question context.
 *
 * @package   local_listcoursefiles
 * @author    Jeremy FitzPatrick
 * @copyright 2022 Te Wānanga o Aotearoa
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question extends course_file {
    /**
     * {@inheritDoc}
     *
     * @throws dml_exception
     */
    #[\Override]
    protected function get_embedding_context(): string|false {
        global $DB;
        $question = $DB->get_record('question', ['id' => $this->itemid]);
        return $question?->{$this->filearea} ?? false;
    }

    #[\Override]
    protected function get_component_url(): moodle_url {
        return new moodle_url('/question/question.php', ['courseid' => $this->courseid, 'id' => $this->itemid]);
    }
}
