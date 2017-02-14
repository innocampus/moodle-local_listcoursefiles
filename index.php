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

require_once(dirname(__FILE__) . '/../../config.php');
require_once('locallib.php');

$courseid = required_param('courseid', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$limit = optional_param('limit', 200, PARAM_INT);
if ($page < 0) {
    $page = 0;
}
if ($limit < 1 || $limit > LISTCOURSEFILES_MAX_FILES) {
    $limit = LISTCOURSEFILES_MAX_FILES;
}
$component = optional_param('component', 'all_wo_submissions', PARAM_ALPHAEXT);
$filetype = optional_param('filetype', 'all', PARAM_ALPHAEXT);
$action = optional_param('action', '', PARAM_ALPHAEXT);

$context = context_course::instance($courseid);
$title = get_string('pluginname', 'local_listcoursefiles');
$url = new moodle_url('/local/listcoursefiles/index.php',
        array('courseid' => $courseid, 'page' => $page, 'limit' => $limit,
              'component' => $component,  'filetype' => $filetype));
$PAGE->set_context($context);
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_url($url);
$PAGE->set_pagelayout('incourse');


require_login($courseid);
require_capability('local/listcoursefiles:view', $context);
$changelicenseallowed = has_capability('local/listcoursefiles:change_license', $context);

$files = new Course_files($courseid, $context, $component, $filetype);

if ($action === 'change_license' && $changelicenseallowed) {
    require_sesskey();
    $chosenfiles = optional_param_array('file', array(), PARAM_INT);
    $license = required_param('license', PARAM_ALPHAEXT);
    try {
        $files->set_files_license($chosenfiles, $license);
    } catch (moodle_exception $e) {
        \core\notification::add($e->getMessage(), \core\output\notification::NOTIFY_ERROR);
    }
}

$filelist = $files->get_file_list($page * $limit, $limit);
$licenses = $files->get_available_licenses();

$tpldata = new stdClass();
$tpldata->course_selection_html = print_course_selection($url, $courseid);
$tpldata->component_selection_html = print_component_selection($url, $files->get_components(), $component);
$tpldata->file_type_selection_html = print_file_type_selection($url, $filetype);
$tpldata->paging_bar_html = $OUTPUT->paging_bar($files->get_file_list_total_size(), $page , $limit, $url, 'page');
$tpldata->url = $url;
$tpldata->sesskey = sesskey();
$tpldata->files = array();
$tpldata->files_exist = count($filelist) > 0;
$tpldata->change_license_allowed = $changelicenseallowed;
$tpldata->license_select_html = html_writer::select($licenses, 'license');

foreach ($filelist as $file) {
    $tplfile = new stdClass();

    $license = (isset($licenses[$file->license])) ? $licenses[$file->license] : '';
    $tplfile->file_license = $files->get_license_name_color($file->license);
    $tplfile->file_id = $file->id;
    $tplfile->file_size = display_size($file->filesize);
    $tplfile->file_type = Course_files::get_file_type_translation($file->mimetype);
    $tplfile->file_uploader = fullname($file);

    $fileurl = $files->get_file_download_url($file);
    $tplfile->file_url = ($fileurl) ? $fileurl->out() : false;
    $tplfile->file_name = $file->filename;

    $componenturl = $files->get_component_url($file->contextlevel, $file->instanceid);
    $tplfile->file_component_url = ($componenturl) ? $componenturl->out() : false;
    $tplfile->file_component = get_component_translation($file->component);

    $tpldata->files[] = $tplfile;
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_listcoursefiles/view', $tpldata);
echo $OUTPUT->footer();
