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
 * @author     Jeremy FitzPatrick
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_file {
    /**
     * @var \stdClass
     */
    protected $file;

    /**
     * @var int
     */
    protected $courseid = 0;

    /* Properties used by the template. */
    /**
     * @var int
     */
    public $fileid = 0;

    /**
     * @var string
     */
    public $filelicense = '';

    /**
     * @var string Friendly readable size of the file (MB or kB as appropriate)
     */
    public $filesize = '';

    /**
     * @var string
     */
    public $filetype = '';

    /**
     * @var string The name of the person who uploaded the file.
     */
    public $fileuploader = '';

    /**
     * @var bool Is the license expired?
     */
    public $fileexpired = false;

    /**
     * @var false|string
     */
    public $fileurl = false;

    /**
     * @var string
     */
    public $filename = '';

    /**
     * @var false|string A link to the page where the file is used.
     */
    public $filecomponenturl = false;

    /**
     * @var string
     */
    public $filecomponent;

    /**
     * @var string|false A link to the page with the editor where the file was added.
     */
    public $fileediturl = false;

    /**
     * @var string A message stating if the file is used or unknown.
     */
    public $fileused;

    /**
     * Creates an object of this class or an appropriate subclass.
     * @param \stdClass $file
     * @return course_file
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function create(\stdClass $file): course_file {
        $classname = '\local_listcoursefiles\components\\' . $file->component;
        if (class_exists($classname)) {
            return new $classname($file);
        }
        return new course_file($file);
    }

    /**
     * course_file constructor.
     * @param \stdClass $file
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function __construct(\stdClass $file) {
        global $COURSE;
        $this->courseid = $COURSE->id;
        $this->file = $file;
        $this->filelicense = licences::get_license_name_color($file->license);
        $this->fileid = $file->id;
        $this->filesize = display_size($file->filesize);
        $this->filetype = mimetypes::get_file_type_translation($file->mimetype);
        $this->fileuploader = fullname($file);
        $this->fileexpired = !$this->check_mimetype_license_expiry_date();

        $fileurl = $this->get_file_download_url();
        $this->fileurl = ($fileurl) ? $fileurl->out() : false;
        $this->filename = $this->get_displayed_filename();

        $componenturl = $this->get_component_url();
        $this->filecomponenturl = ($componenturl) ? $componenturl->out() : false;
        $this->filecomponent = course_files::get_component_translation($file->component);

        $isused = $this->is_file_used();
        if ($isused === true) {
            $this->fileused = get_string('yes', 'core');
        } else if ($isused === false) {
            $this->fileused = get_string('no', 'core');
        } else {
            $this->fileused = get_string('nottested', 'local_listcoursefiles');
        }

        $editurl = $this->get_edit_url();
        $this->fileediturl = ($editurl) ? $editurl->out(false) : false;
    }

    /**
     * Getter for filename
     * @return string
     */
    protected function get_displayed_filename(): string {
        return $this->file->filename;
    }

    /**
     * Check if a file with a specific license has expired.
     *
     * This checks if a file has been expired because:
     *  - it is a document (has a particular mimetype),
     *  - has been provided under a specific license
     *  - and the expiry date is exceed (in respect of the course start date and the file creation time).
     *
     * The following settings need to be defined in the config.php:
     *   array $CFG->fileexpirylicenses which licenses (shortnames) expire
     *   int $CFG->fileexpirydate when do files expire (unix time)
     *   array $CFG->filemimetypes['document'] mime types of documents
     *
     * These adjustments were made by Technische Universität Berlin in order to conform to § 52a UrhG.
     *
     * @return boolean whether file is allowed to be delivered to students
     */
    protected function check_mimetype_license_expiry_date(): bool {
        global $CFG, $COURSE;

        // Check if enabled/configured.
        if (!isset($CFG->fileexpirydate, $CFG->fileexpirylicenses, $CFG->filemimetypes['document'])) {
            return true;
        }

        if (in_array($this->file->license, $CFG->fileexpirylicenses)) {
            $isdoc = false;
            $fmimetype = $this->file->mimetype;
            foreach ($CFG->filemimetypes['document'] as $mime) {
                if ($mime === $fmimetype || (substr($mime, -1) === '%' && strncmp($mime, $fmimetype, strlen($mime) - 1) === 0)) {
                    $isdoc = true;
                    break;
                }
            }
            $coursestart = $COURSE->startdate ?? 0;
            if ($isdoc && $CFG->fileexpirydate > max($coursestart, $this->file->timecreated)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Try to get the download url for a file.
     *
     * @return null|\moodle_url
     * @throws \moodle_exception
     */
    protected function get_file_download_url(): ?\moodle_url {
        if ($this->file->filearea == 'intro') {
            return $this->get_standard_file_download_url();
        }
        return null;
    }

    /**
     * Get the standard download url for a file.
     *
     * Most pluginfile urls are constructed the same way.
     *
     * @param bool $insertitemid
     * @return \moodle_url
     * @throws \moodle_exception
     */
    protected function get_standard_file_download_url(bool $insertitemid = true): \moodle_url {
        $url = '/pluginfile.php/' . $this->file->contextid . '/' . $this->file->component . '/' . $this->file->filearea;
        if ($insertitemid) {
            $url .= '/' . $this->file->itemid;
        }
        $url .= $this->file->filepath . $this->file->filename;
        return new \moodle_url($url);
    }

    /**
     * Try to get the url for the component (module or course).
     *
     * @return null|\moodle_url
     * @throws \moodle_exception
     */
    protected function get_component_url(): ?\moodle_url {
        if ($this->file->contextlevel == CONTEXT_MODULE) {
            $coursemodinfo = get_fast_modinfo($this->courseid);
            if (!empty($coursemodinfo->cms[$this->file->instanceid])) {
                return $coursemodinfo->cms[$this->file->instanceid]->url;
            }
        }
        return null;
    }

    /**
     * Checks if embedded files have been used
     *
     * @return bool|null
     * @throws \dml_exception
     */
    protected function is_file_used(): ?bool {
        global $DB;
        $component = strpos($this->file->component, 'mod_') === 0 ? 'mod' : $this->file->component;
        switch ($component) {
            case 'mod': // Course module.
                $modname = str_replace('mod_', '', $this->file->component);
                if (!array_key_exists($modname, \core_component::get_plugin_list('mod'))) {
                    return null;
                }
                if ($this->file->filearea === 'intro') {
                    $sql = "SELECT m.*
                              FROM {context} ctx
                              JOIN {course_modules} cm ON cm.id = ctx.instanceid
                              JOIN {{$modname}} m ON m.id = cm.instance
                             WHERE ctx.id = ?";
                    $mod = $DB->get_record_sql($sql, [$this->file->contextid]);
                    return $this->is_embedded_file_used($mod, 'intro', $this->file->filename);
                }
                break;
            case 'question':
                $question = $DB->get_record('question', ['id' => $this->file->itemid]);
                return $this->is_embedded_file_used($question, $this->file->filearea, $this->file->filename);
            case 'qtype_essay':
                $question = $DB->get_record('qtype_essay_options', ['questionid' => $this->file->itemid]);
                return $this->is_embedded_file_used($question, 'graderinfo', $this->file->filename);
        }
        return null;
    }

    /**
     * Test if a file is embedded in text
     *
     * @param \stdClass|false $record
     * @param string $field
     * @param string $filename
     * @return bool|null
     */
    protected function is_embedded_file_used($record, string $field, string $filename): ?bool {
        if ($record && property_exists($record, $field)) {
            return is_int(strpos($record->$field, '@@PLUGINFILE@@/' . rawurlencode($filename)));
        }
        return null;
    }

    /**
     * Creates the URL for the editor where the file is added
     *
     * @return \moodle_url|null
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    protected function get_edit_url(): ?\moodle_url {
        global $DB;
        $component = strpos($this->file->component, 'mod_') === 0 ? 'mod' : $this->file->component;
        switch ($component) {
            case 'mod':
                if ($this->file->filearea === 'intro') { // Just checking description for now.
                    $sql = "SELECT cm.*
                              FROM {context} ctx
                              JOIN {course_modules} cm ON cm.id = ctx.instanceid
                             WHERE ctx.id = ?";
                    $mod = $DB->get_record_sql($sql, [$this->file->contextid]);
                    return new \moodle_url('/course/modedit.php?', ['update' => $mod->id]);
                }
                break;
            case 'question':
            case 'qtype_essay':
                return new \moodle_url('/question/question.php?', ['courseid' => $this->courseid, 'id' => $this->file->itemid]);
        }
        return null;
    }
}
