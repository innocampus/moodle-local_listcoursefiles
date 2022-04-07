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

class course_file {
    private $courseid = 0;
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
     * @return null|moodle_url
     */
    public function get_file_download_url($file) {
        switch ($file->component . '#' . $file->filearea) {
            case 'mod_folder#intro':
            case 'mod_folder#content':
            case 'mod_resource#intro':
            case 'mod_resource#content':
                return new \moodle_url('/pluginfile.php/' . $file->contextid . '/' . $file->component . '/' .
                    $file->filearea . '/0' . $file->filepath . $file->filename);

            case 'mod_assign#intro':
            case 'mod_label#intro':
                return new \moodle_url('/pluginfile.php/' . $file->contextid . '/' . $file->component . '/' .
                    $file->filearea . '/' . $file->filepath . $file->filename);

            case 'assignsubmission_file#submission_files':
            case 'mod_assign#introattachment':
            case 'mod_data#content':
            case 'mod_forum#post':
            case 'mod_forum#attachment':
            case 'mod_page#content':
            case 'mod_page#intro':
            case 'mod_glossary#entry':
            case 'mod_wiki#attachments':
            case 'course#section':
                return new \moodle_url('/pluginfile.php/' . $file->contextid . '/' . $file->component . '/' .
                    $file->filearea . '/' . $file->itemid . $file->filepath . $file->filename);

            case 'course#legacy':
                return new \moodle_url('/file.php/' . $this->courseid . $file->filepath . $file->filename);
        }

        return null;
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
            } else if ($file->filearea === 'section') {
                return new \moodle_url('/course/view.php', array(
                    'id' => $this->courseid,
                    'sectionid' => $file->itemid
                ));
            } else {
                return new \moodle_url('/course/info.php', array(
                    'id' => $this->courseid
                ));
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
            case 'course' :
                if ($file->filearea === 'section') {
                    $section = $DB->get_record('course_sections', ['id' => $file->itemid]);
                    $isused = $this->is_embedded_file_used($section, 'summary', $file->filename);
                } else if ($file->filearea === 'overviewfiles') {
                    $isused = true;
                } else if ($file->filearea === 'summary') {
                    $course = $DB->get_record('course', ['id' => $courseid]);
                    $isused = $this->is_embedded_file_used($course, 'summary', $file->filename);
                }
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
                } else {
                    $fn = 'is_file_used_' . $modname;
                    if (is_callable(array($this, $fn))) {
                        $isused = call_user_func(array($this, $fn), $file);
                    } else {
                        $isused = null;
                    }
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
     * Additional file used checks for the Assignment activity module
     * @param object $file
     * @return bool | null
     */
    private function is_file_used_assign($file) {
        // File areas = intro, introattachment.
        if ($file->filearea === 'introattachment') {
            return true;
        }
    }

    /**
     * Additional file used checks for the Book resource module
     * @param object $file
     * @return bool | null
     * @throws \dml_exception
     */
    private function is_file_used_book($file) {
        // File areas = intro, chapter.
        global $DB;
        if ($file->filearea === 'chapter') {
            $chapter = $DB->get_record('book_chapters', ['id' => $file->itemid]);
            $isused = $this->is_embedded_file_used($chapter, 'content', $file->filename);
            return $isused;
        }
    }

    /**
     * Additional file used checks for the Database activity module
     * @param object $file
     * @return bool | null
     * @throws \dml_exception
     */
    private function is_file_used_data($file) {
        // File areas = intro, content.
        global $DB;
        if ($file->filearea === 'content') {
            $sql = 'SELECT * FROM {data_content} dc
                    JOIN {data_fields} df ON df.id = dc.fieldid
                    WHERE dc.id = ?';
            $data = $DB->get_record_sql($sql, [$file->itemid]);
            if ($data->type !== 'textarea' ||
                false !== strpos($data->content, '@@PLUGINFILE@@/' . rawurlencode($file->filename))) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * Additional file used checks for the Feedback activity module
     * @param object $file
     * @return bool | null
     * @throws \dml_exception
     */
    private function is_file_used_feedback($file) {
        // File areas = intro, item, page_after_submit.
        global $DB;
        if ($file->filearea === 'item') {
            $item = $DB->get_record('feedback_item', ['id' => $file->itemid]);
            $isused = $this->is_embedded_file_used($item, 'presentation', $file->filename);
            return $isused;
        } else if ($file->filearea = 'page_after_submit') {
            $sql = 'SELECT m.* FROM {feedback} m
                    JOIN {course_modules} cm ON cm.instance = m.id
                    JOIN {context} ctx ON ctx.instanceid = cm.id
                    WHERE ctx.id = ?';
            $feedback = $DB->get_record_sql($sql, [$file->contextid]);
            $isused = $this->is_embedded_file_used($feedback, 'page_after_submit', $file->filename);
            return $isused;
        }
    }

    /**
     * Additional file used checks for Folder resource module
     * @return bool
     */
    private function is_file_used_folder() {
        // File areas = intro, content.
        return true;
    }

    /**
     * Additional file used checks for the Forum activity module
     * @param object $file
     * @return bool | null
     * @throws \dml_exception
     */
    private function is_file_used_forum($file) {
        // File areas = intro, post.
        global $DB;
        if ($file->filearea === 'post') {
            $post = $DB->get_record('forum_posts', ['id' => $file->itemid]);
            $isused = $this->is_embedded_file_used($post, 'message', $file->filename);
            return $isused;
        } else if ($file->filearea === 'attachment') {
            return true;
        }
    }

    /**
     * Additional file used checks for the H5P activity module
     * @return bool
     */
    private function is_file_used_h5pactivity() {
        // File areas = intro, package.
        return true;
    }

    /**
     * Additional file used checks for the Page resource module
     * @param object $file
     * @return bool | null
     * @throws \dml_exception
     */
    private function is_file_used_page($file) {
        // File areas = intro, content.
        global $DB;
        if ($file->filearea === 'content') {
            $sql = 'SELECT m.* FROM {page} m
                    JOIN {course_modules} cm ON cm.instance = m.id
                    JOIN {context} ctx ON ctx.instanceid = cm.id
                    WHERE ctx.id = ?';
            $page = $DB->get_record_sql($sql, [$file->contextid]);
            $isused = $this->is_embedded_file_used($page, 'content', $file->filename);
            return $isused;
        }
    }

    /**
     * Additional file used checks for the Resource module
     * @return bool
     */
    private function is_file_used_resource() {
        // File areas = intro, content.
        return true;
    }

    private function is_file_used_glossary($file) {
        // File areas = intro, content.
        global $DB;
        if ($file->filearea === 'attachment') {
            return true;
        } else if ($file->filearea === 'entry') {
            $entry = $DB->get_record('glossary_entries', ['id' => $file->itemid]);
            $isused = $this->is_embedded_file_used($entry, 'definition', $file->filename);
            return $isused;
        }
    }

    /**
     * Test if a file is embbeded in text
     *
     * @param object $record
     * @param string $field
     * @param string $filename
     * @return bool|null
     */
    private function is_embedded_file_used($record, $field, $filename) {
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
            case 'course' :
                if ($file->filearea === 'section') {
                    $url = new \moodle_url('/course/editsection.php?', ['id' => $file->itemid]);
                } else if ($file->filearea === 'overviewfiles' || $file->filearea === 'summary') {
                    $url = new \moodle_url('/course/edit.php?', ['id' => $this->courseid]);
                }
                break;
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
