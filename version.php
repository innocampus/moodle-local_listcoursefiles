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
 * Version info
 *
 * This File contains information about the current version of local/listcoursefiles
 *
 * @package   local_listcoursefiles
 * @copyright 2016 Martin Gauk (@innoCampus, TU Berlin)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * {@noinspection PhpUndefinedVariableInspection}
 */

defined('MOODLE_INTERNAL') || die;

$plugin->version   = 2025030500;
$plugin->requires  = 2022112800;  // Require at least Moodle 4.1.0.
$plugin->cron      = 0;
$plugin->component = "local_listcoursefiles";
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = "1.5.0";
