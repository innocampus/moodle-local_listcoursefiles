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
 * @package    local_listcoursefiles
 * @copyright  2017 Martin Gauk (@innoCampus, TU Berlin)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$limit = optional_param('limit', 200, PARAM_INT);
if ($page < 0) {
    $page = 0;
}
if ($limit < 1 || $limit > local_listcoursefiles\course_files::MAX_FILES) {
    $limit = local_listcoursefiles\course_files::MAX_FILES;
}
$component = optional_param('component', 'all_wo_submissions', PARAM_ALPHANUMEXT);
$filetype = optional_param('filetype', 'all', PARAM_ALPHAEXT);
$action = optional_param('action', '', PARAM_ALPHAEXT);
$chosenfiles = optional_param_array('file', [], PARAM_INT);

$context = context_course::instance($courseid);
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
$PAGE->set_context($context);
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_url($url);
$PAGE->set_pagelayout('incourse');


require_login($courseid);
require_capability('local/listcoursefiles:view', $context);
$changelicenseallowed = has_capability('local/listcoursefiles:change_license', $context);
$downloadallowed = has_capability('local/listcoursefiles:download', $context);

$files = new local_listcoursefiles\course_files($courseid, $context, $component, $filetype);

if ($action === 'change_license' && $changelicenseallowed) {
    require_sesskey();
    $license = required_param('license', PARAM_ALPHAEXT);
    try {
        $files->set_files_license($chosenfiles, $license);
    } catch (moodle_exception $e) {
        \core\notification::add($e->getMessage(), \core\output\notification::NOTIFY_ERROR);
    }
} else if ($action === 'download' && $downloadallowed) {
    require_sesskey();
    try {
        $files->download_files($chosenfiles);
    } catch (moodle_exception $e) {
        \core\notification::add($e->getMessage(), \core\output\notification::NOTIFY_ERROR);
    }
}

$filelist = $files->get_file_list($page * $limit, $limit);
$renderer = $PAGE->get_renderer('local_listcoursefiles');

echo $OUTPUT->header();
echo $renderer->overview_page($url, $files, $page, $limit, $filelist, $changelicenseallowed, $downloadallowed);
echo $OUTPUT->footer();
