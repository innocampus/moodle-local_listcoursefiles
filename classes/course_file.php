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
 * Class course_file
 * @package local_listcoursefiles
 * @copyright  2017 Martin Gauk (@innoCampus, TU Berlin)
 * @copyright 2022 Jeremy FitzPatrick
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_file {
    /**
     * @var
     */
    protected $courseid = 0;

    /**
     * course_file constructor.
     * @param object $file
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function __construct($file) {
        global $COURSE;
        $this->courseid = $COURSE->id;
        $this->file_license = licences::get_license_name_color($file->license);
        $this->file_id = $file->id;
        $this->file_size = display_size($file->filesize);
        $this->file_type = mimetypes::get_file_type_translation($file->mimetype);
        $this->file_uploader = fullname($file);
        $this->file_expired = !$this->check_mimetype_license_expiry_date($file);

        $fileurl = $this->get_file_download_url($file);
        $this->file_url = ($fileurl) ? $fileurl->out() : false;
        $this->file_name = $file->filename;

        $componenturl = $this->get_component_url($file);
        $this->file_component_url = ($componenturl) ? $componenturl->out() : false;
        $this->file_component = local_listcoursefiles_get_component_translation($file->component);

        $isused = $this->is_file_used($file, $COURSE->id);

        if ($isused === true) {
            $this->file_used = get_string('yes', 'core');
        } else if ($isused === false) {
            $this->file_used = get_string('no', 'core');
        } else {
            $this->file_used = get_string('nottested', 'local_listcoursefiles');
        }

        $this->file_editurl = $this->get_edit_url($file);
    }

    /**
     * Check if a file with a specific license has expired.
     *
     * This checks if a file has been expired because:
     *  - it is a document (has a particular mimetype),
     *  - has been provided under a specific license
     *  - and the expiry date is exceed (in respect of the coure start date and the file creation time).
     *
     * The following settings need to be defined in the config.php:
     *   array $CFG->fileexpirylicenses which licenses (shortnames) expire
     *   int $CFG->fileexpirydate when do files expire (unix time)
     *   array $CFG->filemimetypes['document'] mime types of documents
     *
     * These adjustments were made by Technische Universität Berlin in order to conform to § 52a UrhG.
     *
     * @param stdClass $file
     * @return boolean whether file is allowed to be delivered to students
     */
    public function check_mimetype_license_expiry_date($file) {
        global $CFG, $COURSE;

        // Check if enabled/configured.
        if (!isset($CFG->fileexpirydate, $CFG->fileexpirylicenses, $CFG->filemimetypes['document'])) {
            return true;
        }

        if (in_array($file->license, $CFG->fileexpirylicenses)) {
            $isdoc = false;
            $fmimetype = $file->mimetype;
            foreach ($CFG->filemimetypes['document'] as $mime) {
                if ($mime === $fmimetype || (substr($mime, -1) === '%' && strncmp($mime, $fmimetype, strlen($mime) - 1) === 0)) {
                    $isdoc = true;
                    break;
                }
            }
            $coursestart = isset($COURSE->startdate) ? $COURSE->startdate : 0;
            if ($isdoc && $CFG->fileexpirydate > max($coursestart, $file->timecreated)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Try to get the download url for a file.
     *
     * @param array $file
     * @return null|\moodle_url
     */
    public function get_file_download_url($file) {
        return new \moodle_url('/pluginfile.php/' . $file->contextid . '/' . $file->component . '/' .
            $file->filearea . '/0' . $file->filepath . $file->filename);
    }

    /**
     * Try to get the url for the component (module or course).
     *
     * @param object $file
     * @return null|\moodle_url
     * @throws moodle_exception
     */
    public function get_component_url($file) {
        if ($file->contextlevel == CONTEXT_MODULE) {
            if (!empty($this->coursemodinfo->cms[$file->instanceid])) {
                return $this->coursemodinfo->cms[$file->instanceid]->url;
            }
        } else if ($file->contextlevel == CONTEXT_COURSE) {
            if ($file->component === 'contentbank') {
                return new \moodle_url('/contentbank/index.php', ['contextid' => $file->contextid]);
            }
        }

        return null;
    }

    /**
     * Checks if embedded files have been used
     *
     * @param object $file
     * @param integer $courseid
     * @return bool
     * @throws \dml_exception
     */
    public function is_file_used($file, $courseid) {
        global $DB;
        $isused = false;
        $component = strpos($file->component, 'mod_') === 0 ? 'mod' : $file->component;

        switch ($component) {
            case 'contentbank' :
                $isused = null;
                break;

            case 'mod' : // Course module.
                $modname = str_replace('mod_', '', $file->component);
                if ($file->filearea === 'intro') {
                    $sql = 'SELECT m.* FROM {context} ctx
                            JOIN {course_modules} cm ON cm.id = ctx.instanceid
                            JOIN {' . $modname . '} m ON m.id = cm.instance
                            WHERE ctx.id = ?';
                    $mod = $DB->get_record_sql($sql, [$file->contextid]);
                    $isused = $this->is_embedded_file_used($mod, 'intro', $file->filename);
                }
                break;
            case 'question' :
                $question = $DB->get_record('question', ['id' => $file->itemid]);
                $isused = $this->is_embedded_file_used($question, $file->filearea, $file->filename);
                break;
            case 'qtype_essay' :
                $question = $DB->get_record('qtype_essay_options', ['questionid' => $file->itemid]);
                $isused = $this->is_embedded_file_used($question, 'graderinfo', $file->filename);
                break;
            default :
                $isused = null;
        }
        return $isused;
    }

    /**
     * Test if a file is embbeded in text
     *
     * @param object $record
     * @param string $field
     * @param string $filename
     * @return bool|null
     */
    protected function is_embedded_file_used($record, $field, $filename) {
        if ($record && property_exists($record, $field)) {
            return is_int(strpos($record->$field, '@@PLUGINFILE@@/' . rawurlencode($filename)));
        } else {
            return null;
        }
    }

    /**
     * Creates the URL for the editor where the file is added
     *
     * @param object $file
     * @return \moodle_url|string
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_edit_url($file) {
        global $DB;
        $url = '';
        $component = strpos($file->component, 'mod_') === 0 ? 'mod' : $file->component;

        switch ($component) {

            case 'mod' :
                if ($file->filearea === 'intro') { // Just checking description for now.
                    $sql = 'SELECT cm.* FROM {context} ctx
                        JOIN {course_modules} cm ON cm.id = ctx.instanceid
                        WHERE ctx.id = ?';
                    $mod = $DB->get_record_sql($sql, [$file->contextid]);
                    $url = new \moodle_url('/course/modedit.php?', ['update' => $mod->id]);
                }
                break;
            case 'question' :
            case 'qtype_essay' :
                $url = new \moodle_url('/question/question.php?', ['courseid' => $this->courseid, 'id' => $file->itemid]);
                $url = $url->out(false);
                break;
            default :
                $url = '';
        }
        return $url;
    }
}
