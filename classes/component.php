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

use core\exception\coding_exception;

/**
 * Helper class for component options.
 *
 * @package   local_listcoursefiles
 * @copyright 2017 Martin Gauk (@innoCampus, TU Berlin)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class component {
    /** @var string Special filter option representing all components with files. */
    public const ALL = 'all';

    /** @var string Special filter option representing all components with files other than file submissions. */
    public const ALL_WITHOUT_SUBMISSIONS = 'all_without_submissions';

    /**
     * Returns the human-readable name (translated) of the given component if possible.
     *
     * @param string $name Name of the component or one of the special cases defined in this class.
     * @return string Component display name or `$name` if no language string is available.
     * @throws coding_exception
     */
    public static function get_display_name(string $name): string {
        if ($name === self::ALL) {
            return get_string('all_files', 'local_listcoursefiles');
        }
        if ($name === self::ALL_WITHOUT_SUBMISSIONS) {
            return get_string('all_without_submissions', 'local_listcoursefiles');
        }
        if (get_string_manager()->string_exists('pluginname', $name)) {
            return get_string('pluginname', $name);
        }
        if (get_string_manager()->string_exists($name, '')) {
            return get_string($name);
        }
        return $name;
    }

    /**
     * Derives the variant from an optional 'component' GET/POST parameter.
     *
     * @param string $default Default to return if the parameter is not provided.
     * @return string Query parameter value.
     * @throws coding_exception
     */
    public static function optional_param(string $default = self::ALL_WITHOUT_SUBMISSIONS): string {
        return optional_param('component', $default, PARAM_ALPHANUMEXT);
    }
}
