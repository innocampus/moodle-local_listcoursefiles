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
use local_listcoursefiles\course_file;
use moodle_url;

/**
 * Represents a file uploaded in a course context.
 *
 * @package   local_listcoursefiles
 * @author    Jeremy FitzPatrick
 * @copyright 2022 Te Wānanga o Aotearoa
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course extends course_file {
    #[\Override]
    protected function get_download_url(): moodle_url|null {
        return match ($this->filearea) {
            'legacy'        => new moodle_url('/file.php/' . $this->courseid . $this->filepath . $this->filename),
            'overviewfiles' => $this->get_standard_download_url(insertitemid: false),
            'section'       => $this->get_standard_download_url(),
            default         => parent::get_download_url(),
        };
    }

    #[\Override]
    protected function get_component_url(): moodle_url {
        if ($this->filearea === 'section') {
            return new moodle_url('/course/view.php', ['id' => $this->courseid, 'sectionid' => $this->itemid]);
        }
        return new moodle_url('/course/info.php', ['id' => $this->courseid]);
    }

    /**
     * {@inheritDoc}
     *
     * @throws moodle_exception
     */
    #[\Override]
    protected function get_edit_url(): moodle_url|null {
        return match ($this->filearea) {
            'overviewfiles', 'summary' => new moodle_url('/course/edit.php', ['id' => $this->courseid]),
            'section'                  => new moodle_url('/course/editsection.php', ['id' => $this->itemid]),
            default                    => parent::get_edit_url(),
        };
    }

    #[\Override]
    protected function is_used(): bool|null {
        if ($this->filearea === 'overviewfiles') {
            return true;
        }
        return parent::is_used();
    }

    /**
     * {@inheritDoc}
     *
     * @throws dml_exception
     */
    #[\Override]
    protected function get_embedding_context(): string|false {
        global $DB;
        return match ($this->filearea) {
            'section' => $DB->get_field('course_sections', 'summary', ['id' => $this->itemid]),
            'summary' => $DB->get_field('course', 'summary', ['id' => $this->courseid]),
            default   => parent::get_embedding_context(),
        };
    }
}
