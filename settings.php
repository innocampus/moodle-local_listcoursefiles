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
 * Administration settings definitions for local listcoursefiles.
 *
 * @package    local_listcoursefiles
 * @copyright  2016 Martin Gauk (@innoCampus, TU Berlin)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/listcoursefiles/lib.php');

if ($hassiteconfig) {
    $licenses = local_listcoursefiles\course_files::get_available_licenses();
    $licensenames = '';
    foreach ($licenses as $short => $full) {
        $licensenames .= "$full ($short), ";
    }
    $licensenames = substr($licensenames, 0, -2);

    $settings = new admin_settingpage('local_listcoursefiles',
            get_string('pluginname', 'local_listcoursefiles'), 'moodle/site:config');
    $settings->add(new admin_setting_configtextarea('local_listcoursefiles/licensecolors',
            get_string('license_colors', 'local_listcoursefiles'),
            get_string('license_colors_desc', 'local_listcoursefiles', $licensenames),
            ''));

    $ADMIN->add('localplugins', $settings);
}

