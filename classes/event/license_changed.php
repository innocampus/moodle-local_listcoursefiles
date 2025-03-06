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
 * The local_listcoursefiles license changed event.
 *
 * @package   local_listcoursefiles
 * @copyright 2016 Martin Gauk (@innoCampus, TU Berlin)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_listcoursefiles\event;

use coding_exception;
use core\event\base as event_base;

/**
 * The local_listcoursefiles license changed event class.
 *
 * @package   local_listcoursefiles
 * @copyright 2016 Martin Gauk (@innoCampus, TU Berlin)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class license_changed extends event_base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'files';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '$this->userid' changed the license of file with id '$this->objectid' ".
        "to '" . $this->other['license'] . "'.";
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     * @throws coding_exception
     */
    public static function get_name(): string {
        return get_string('eventlicensechanged', 'local_listcoursefiles');
    }

    /**
     * Returns relevant URL.
     *
     * @return null
     */
    public function get_url() {
        return null;
    }
}
