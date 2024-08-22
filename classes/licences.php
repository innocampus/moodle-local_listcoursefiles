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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/licenselib.php');

use coding_exception;
use dml_exception;
use html_writer;
use license_manager;

/**
 * Class licences
 * @package local_listcoursefiles
 * @copyright  2017 Martin Gauk (@innoCampus, TU Berlin)
 * @author Jeremy FitzPatrick
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class licences {
    /**
     * @var string[]|null
     */
    static protected ?array $licenses = null;

    /**
     * @var string[]|null
     */
    static protected ?array $licenscolors = null;

    /**
     * Returns an associative array of active licenses with short name keys and full name values.
     *
     * Caches the array after the first call.
     *
     * @return string[]
     * @throws coding_exception
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public static function get_available_licenses(): array {
        if (is_null(self::$licenses)) {
            self::$licenses = license_manager::get_active_licenses();
            array_walk(
                self::$licenses,
                fn(object &$license, string $shortname) => $license = $license->fullname,
            );
        }
        return self::$licenses;
    }

    /**
     * Returns full license name wrapped in an HTML `span` with its configured background color.
     *
     * If no color was set for the specified license, the full license name is returned as is.
     * If no license with the specified `shortname` is available, an empty string is returned.
     *
     * Caches all configured license colors after the first call.
     *
     * @param string $licenseshort short name of a license
     * @return string full name of the license with HTML
     * @throws dml_exception|coding_exception
     */
    public static function get_license_name_color(string $licenseshort): string {
        if (is_null(self::$licenscolors)) {
            $colorscfg = get_config('local_listcoursefiles', 'licensecolors');
            preg_match_all('@\s*(\S+)\s*([a-fA-F0-9]{6})\s*@', $colorscfg, $matches, PREG_SET_ORDER);
            self::$licenscolors = array_combine(array_column($matches, 1), array_column($matches, 2));
        }
        $name = self::get_available_licenses()[$licenseshort] ?? '';
        if ($color = self::$licenscolors[$licenseshort] ?? null) {
            $name = html_writer::tag('span', $name, ['style' => "background-color: #$color"]);
        }
        return $name;
    }
}
