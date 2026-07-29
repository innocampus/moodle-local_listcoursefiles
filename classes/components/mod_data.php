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
use moodle_url;

/**
 * Represents a file uploaded in a data module context.
 *
 * @package   local_listcoursefiles
 * @author    Jeremy FitzPatrick
 * @copyright 2022 Te Wānanga o Aotearoa
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_data extends mod {
    #[\Override]
    protected function get_download_url(): moodle_url|null {
        if ($this->filearea == 'content') {
            return $this->get_standard_download_url();
        }
        return parent::get_download_url();
    }

    /**
     * {@inheritDoc}
     *
     * @throws dml_exception
     */
    #[\Override]
    protected function is_used(): bool|null {
        global $DB;
        if ($this->filearea === 'content') {
            $sql = "SELECT df.type, dc.content
                      FROM {data_content} dc
                      JOIN {data_fields} df ON df.id = dc.fieldid
                     WHERE dc.id = :contentid";
            $data = $DB->get_record_sql($sql, ['contentid' => $this->itemid]);
            if (!$data) {
                return null;
            }
            if ($data->type !== 'textarea') {
                return true;
            }
            if (is_null($data->content)) {
                return null;
            }
            return parent::is_embedded_in($data->content);
        }
        // Parent implementation will check for embedding in the `intro` file area.
        return parent::is_used();
    }
}
