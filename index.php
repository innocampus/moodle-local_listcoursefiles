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
 * List all files in a course.
 *
 * @package   local_listcoursefiles
 * @copyright 2017 Martin Gauk (@innoCampus, TU Berlin)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * {@noinspection PhpUnhandledExceptionInspection}
 */

use core\exception\moodle_exception;
use core\notification;
use local_listcoursefiles\course_files;
use local_listcoursefiles\filetype;

require_once(dirname(__FILE__) . '/../../config.php');

global $OUTPUT, $PAGE;

$courseid = required_param('courseid', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$limit = optional_param('limit', 200, PARAM_INT);
if ($page < 0) {
    $page = 0;
}
if ($limit < 1 || $limit > course_files::MAX_FILES) {
    $limit = course_files::MAX_FILES;
}
$component = optional_param('component', 'all_without_submissions', PARAM_ALPHANUMEXT);
$filetype = optional_param('filetype', filetype::all->value, PARAM_ALPHAEXT);
$action = optional_param('action', '', PARAM_ALPHAEXT);

$coursefiles = new course_files(
    courseid: $courseid,
    component: $component,
    filetype: filetype::from($filetype), // TODO: Handle invalid filetype more gracefully.
    offset: $page * $limit,
    limit: $limit,
);
$title = get_string('pluginname', 'local_listcoursefiles');
$url = new moodle_url(
    '/local/listcoursefiles/index.php',
    [
        'courseid' => $courseid,
        'page' => $page,
        'limit' => $limit,
        'component' => $component,
        'filetype' => $filetype,
    ],
);
$PAGE->set_context($coursefiles->context);
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_url($url);
$PAGE->set_pagelayout('incourse');

require_login($courseid);
require_capability('local/listcoursefiles:view', $coursefiles->context);

if ($action === 'change_license') {
    require_capability('local/listcoursefiles:change_license', $coursefiles->context);
    require_sesskey();
    $license = required_param('license', PARAM_NOTAGS);
    $chosenfiles = array_keys(required_param_array('file', PARAM_INT));
    try {
        $coursefiles->set_license($license, ...$chosenfiles);
    } catch (moodle_exception $e) {
        notification::error($e->getMessage());
    }
} else if ($action === 'download') {
    require_capability('local/listcoursefiles:download', $coursefiles->context);
    require_sesskey();
    $chosenfiles = array_keys(required_param_array('file', PARAM_INT));
    try {
        $coursefiles->download(...$chosenfiles);
    } catch (moodle_exception $e) {
        notification::error($e->getMessage());
    }
}

/** @var local_listcoursefiles\output\renderer $renderer */
$renderer = $PAGE->get_renderer('local_listcoursefiles');

echo $OUTPUT->header();
echo $renderer->overview_page($url, $coursefiles, $page);
echo $OUTPUT->footer();
