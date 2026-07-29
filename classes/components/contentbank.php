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
use file_storage;
use local_listcoursefiles\course_file;
use moodle_url;
use stored_file;

/**
 * Represents a file in the content bank.
 *
 * @package   local_listcoursefiles
 * @author    Jeremy FitzPatrick
 * @copyright 2022 Te Wānanga o Aotearoa
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contentbank extends course_file {
    /**
     * {@inheritDoc}
     *
     * @throws dml_exception
     */
    #[\Override]
    protected function get_displayname(): string {
        global $DB;
        return $DB->get_field('contentbank_content', 'name', ['id' => $this->itemid]) ?: parent::get_displayname();
    }

    #[\Override]
    protected function get_download_url(): moodle_url {
        return $this->get_standard_download_url();
    }

    #[\Override]
    protected function get_component_url(): moodle_url {
        return new moodle_url('/contentbank/index.php', ['contextid' => $this->contextid]);
    }

    #[\Override]
    protected function is_used(): bool {
        $fs = new file_storage();
        $f = new stored_file($fs, (object) (array) $this);
        return $fs->get_references_count_by_storedfile($f) > 1;
    }
}
