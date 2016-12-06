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
    $files->set_files_license($chosenfiles, $license);
}


$filelist = $files->get_file_list($page * $limit, $limit);

echo $OUTPUT->header();

echo '<div class="local_listcoursefiles_menu">';
echo '<div class="local_listcoursefiles_selection">';
echo print_course_selection($url, $courseid) . '</div>';
echo '<div class="local_listcoursefiles_selection">';
echo print_component_selection($url, $files->get_components(), $component) . '</div>';
echo '<div class="local_listcoursefiles_selection">';
echo print_file_type_selection($url, $filetype) . '</div>';
echo '</div>';

echo '<div class="local_listcoursefiles_description">';
echo get_string('description', 'local_listcoursefiles');
echo '</div>';

echo $OUTPUT->paging_bar($files->get_file_list_total_size(), $page , $limit, $url, 'page');
echo '<form action="' . $url . '" method="post" id="filelist">';
echo '<input name="sesskey" type="hidden" value="' . sesskey() . '" />';


$table = new html_table();
$table->head = array();
$table->head[] = '<input type="checkbox" id="check_all" >';
$table->head[] = get_string('filename', 'local_listcoursefiles');
$table->head[] = get_string('filesize', 'local_listcoursefiles');
$table->head[] = get_string('component', 'local_listcoursefiles');
$table->head[] = get_string('mimetype', 'local_listcoursefiles');
$table->head[] = get_string('license', 'local_listcoursefiles');
$table->head[] = get_string('uploader', 'local_listcoursefiles');

$table->align = array('left');
$table->attributes = array('align' => 'center');
$table->data = array();

$licenses = $files->get_available_licenses();
foreach ($filelist as $f) {
    $license = (isset($licenses[$f->license])) ? $licenses[$f->license] : '';
    $checkbox = '<input type="checkbox" class="checkbox" name="file[' . $f->id . ']" />';

    $fileurl = $files->get_file_download_url($f);
    $filenameurl = $f->filename;
    if (!is_null($fileurl)) {
        $filenameurl = '<a href="' . $fileurl->out() . '">' . $f->filename . '</a>';
    }

    $componenturl = $files->get_component_url($f->contextlevel, $f->instanceid);
    $componentnameurl = get_component_translation($f->component);
    if (!is_null($componenturl)) {
        $componentnameurl = '<a href="' . $componenturl->out() . '">' . $componentnameurl . '</a>';
    }

    $table->data[] = array(
        $checkbox, $filenameurl, display_size($f->filesize), $componentnameurl,
        Course_files::get_file_type_translation($f->mimetype), $license, fullname($f)
    );
}

echo html_writer::table($table);

if ($changelicenseallowed) {
    echo html_writer::select($licenses, 'license');
    echo '<button type="submit" name="action" value="change_license">';
    echo get_string('change_license', 'local_listcoursefiles');
    echo '</button>';
}

echo '</form>';

// Checkbox that toggles all other checkboxes.
$PAGE->requires->js_amd_inline("
require(['jquery'], function($) {
    $('#check_all').click(function () {
        $('input:checkbox').prop('checked', this.checked);
    });
});
");

echo $OUTPUT->footer();
