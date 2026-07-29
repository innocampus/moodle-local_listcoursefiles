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

/**
 * Definition of the {@see \local_listcoursefiles\event\license_changed} class.
 *
 * @package   local_listcoursefiles
 * @copyright 2016 Martin Gauk (@innoCampus, TU Berlin)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_listcoursefiles\event;

use coding_exception;
use core\context\course as context_course;
use core\event\base as event_base;
use core\lang_string;
use local_listcoursefiles\course_file;

/**
 * Event for when a user changes the license of a file.
 *
 * @property-read string $license Short name of the license set for the file.
 *
 * @package   local_listcoursefiles
 * @copyright 2016 Martin Gauk (@innoCampus, TU Berlin)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class license_changed extends event_base {
    /**
     * Properly typed constructor.
     *
     * @param context_course $context Context of the course the file belongs to.
     * @param course_file $file File that was changed.
     * @param string $license Short name of the license set for the file.
     * @return self New event instance.
     * @throws coding_exception
     */
    public static function instance(context_course $context, course_file $file, string $license): self {
        return self::create([
            'context' => $context,
            'objectid' => $file->id,
            'other' => ['license' => $license],
        ]);
    }

    #[\Override]
    public function __get($name) {
        if ($name === 'license') {
            return $this->data['other']['license'];
        }
        return parent::__get($name);
    }

    #[\Override]
    protected function init(): void {
        $this->data['objecttable'] = 'files';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    #[\Override]
    public function get_description(): string {
        return "The user with id '$this->userid' changed the license of file with id '$this->objectid' to '$this->license'.";
    }

    #[\Override]
    public static function get_name(): lang_string {
        return new lang_string('event:license_changed', 'local_listcoursefiles');
    }

    #[\Override]
    public function get_url(): null {
        return null;
    }
}
