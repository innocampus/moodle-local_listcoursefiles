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

namespace local_listcoursefiles;

/**
 * Class licences
 * @package local_listcoursefiles
 * @copyright  2017 Martin Gauk (@innoCampus, TU Berlin)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class licences {
    /**
     * @var null
     */
    static protected $licenses = null;

    /**
     * @var null
     */
    static protected $licenscolors = null;

    /**
     * Remember licences as array
     *
     * @return array|null
     * @throws \coding_exception
     */
    public static function get_available_licenses() {
        global $CFG;

        if (self::$licenses === null) {
            self::$licenses = array();
            $a = explode(',', $CFG->licenses);
            foreach ($a as $license) {
                self::$licenses[$license] = \get_string($license, 'license');
            }
        }

        return self::$licenses;
    }

    /**
     * Wraps license name in span element with background color as per plugin settings or
     * retuns license name if no color set
     *
     * @param string $licenseshort short name of a license
     * @return string full name of the license with HTML
     * @throws dml_exception|coding_exception
     */
    public static function get_license_name_color($licenseshort) {
        if (self::$licenscolors === null) {
            self::get_available_licenses();
            $colorscfg = get_config('local_listcoursefiles', 'licensecolors');
            $matches = array();
            preg_match_all('@\s*(\S+)\s*([a-fA-F0-9]{6})\s*@', $colorscfg, $matches, PREG_SET_ORDER);
            self::$licenscolors = array();
            foreach ($matches as $m) {
                self::$licenscolors[$m[1]] = $m[2];
            }
        }

        $name = (isset(self::$licenses[$licenseshort])) ? self::$licenses[$licenseshort] : '';
        if (isset(self::$licenscolors[$licenseshort])) {
            $name = \html_writer::tag('span', $name, array('style' => 'background-color: #' . self::$licenscolors[$licenseshort]));
        }
        return $name;
    }
}
