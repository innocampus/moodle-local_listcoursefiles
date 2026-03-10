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
 * Plugin settings.
 *
 * @link https://moodledev.io/docs/apis/subsystems/admin Admin settings Moodle docs
 *
 * @package   local_listcoursefiles
 * @copyright 2016 Martin Gauk (@innoCampus, TU Berlin)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * {@noinspection PhpUnhandledExceptionInspection}
 */

use local_listcoursefiles\licences;

defined('MOODLE_INTERNAL') || die();

global $ADMIN, $hassiteconfig;

if ($hassiteconfig) {
    $licenses = licences::get_available_licenses();
    array_walk($licenses, fn (string &$value, string $key) => $value .= " ($key)");
    $settings = new admin_settingpage(
        name: 'local_listcoursefiles',
        visiblename: new lang_string('pluginname', 'local_listcoursefiles'),
        req_capability: 'moodle/site:config',
    );
    $settings->add(
        new admin_setting_configtextarea(
            name: 'local_listcoursefiles/licensecolors',
            visiblename: new lang_string('license_colors', 'local_listcoursefiles'),
            description: new lang_string('license_colors_desc', 'local_listcoursefiles', implode(', ', $licenses)),
            defaultsetting: '',
        )
    );
    /** @var admin_root $ADMIN */
    $ADMIN->add('localplugins', $settings);
}
