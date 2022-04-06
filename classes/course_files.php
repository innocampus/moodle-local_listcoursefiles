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
 * Internal API of local listcoursefiles.
 *
 * @package    local_listcoursefiles
 * @copyright  2017 Martin Gauk (@innoCampus, TU Berlin)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_listcoursefiles;

/**
 * Class course_files
 * @package local_listcoursefiles
 */
class course_files {
    /**
     * @var context
     */
    protected $context;

    /**
     * @var int
     */
    protected $filescount = -1;

    /**
     * @var array
     */
    protected $components = null;

    /**
     * @var array
     */
    protected $filelist = null;

    /**
     * @var null
     */
    static protected $licenses = null;

    /**
     * @var null
     */
    static protected $licenscolors = null;

    /**
     * @var string
     */
    protected $filtercomponent;

    /**
     * @var string
     */
    protected $filterfiletype;

    /**
     * @var course_modinfo
     */
    protected $coursemodinfo;

    /**
     * @var int
     */
    protected $courseid;

    /**
     * Mapping of file types to possible mime types.
     * @var array
     */
    static protected $mimetypes = array(
        'document' => array('application/epub+zip', 'application/msword', 'application/pdf',
            'application/postscript', 'application/vnd.ms-%', 'application/vnd.oasis.opendocument%',
            'application/vnd.openxmlformats-officedocument%', 'application/vnd.sun.xml%',
            'application/x-digidoc', 'application/xhtml+xml', 'application/x-javascript',
            'application/x-latex', 'application/xml', 'application/x-ms%', 'application/x-tex%',
            'document%', 'spreadsheet', 'text/%'),
        'image' => array('image/%'),
        'audio' => array('audio/%'),
        'video' => array('video/%'),
        'archive' => array('application/zip', 'application/x-tar', 'application/g-zip',
            'application/x-rar-compressed', 'application/x-7z-compressed', 'application/vnd.moodle.backup'),
    );

    /**
     * course_files constructor.
     * @param integer $courseid
     * @param \context $context
     * @param string $component
     * @param string $filetype
     * @throws \moodle_exception
     */
    public function __construct($courseid, \context $context, $component, $filetype) {
        $this->courseid = $courseid;
        $this->context = $context;
        $this->filtercomponent = $component;
        $this->filterfiletype = $filetype;
        $this->coursemodinfo = get_fast_modinfo($courseid);
    }

    /**
     * Retrieve the files within a course/context.
     *
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public function get_file_list($offset, $limit) {
        global $DB;

        if ($this->filelist !== null) {
            return $this->filelist;
        }

        $availcomponents = $this->get_components();
        $sqlwhere = '';
        $sqlwherecomponent = '';
        if ($this->filtercomponent === 'all_wo_submissions') {
            $sqlwhere .= 'AND f.component NOT LIKE :component';
            $sqlwherecomponent = 'assign%';
        } else if ($this->filtercomponent !== 'all' && isset($availcomponents[$this->filtercomponent])) {
            $sqlwhere .= 'AND f.component LIKE :component';
            $sqlwherecomponent = $this->filtercomponent;
        }

        if ($this->filterfiletype === 'other') {
            $sqlwhere .= ' AND ' . $this->get_sql_mimetype(array_keys(self::$mimetypes), false);
        } else if (isset(self::$mimetypes[$this->filterfiletype])) {
            $sqlwhere .= ' AND ' . $this->get_sql_mimetype($this->filterfiletype, true);
        }

        $usernamefields = get_all_user_name_fields(true, 'u');

        $sql = 'FROM {files} f
                LEFT JOIN {context} c ON (c.id = f.contextid)
                LEFT JOIN {user} u ON (u.id = f.userid)
                WHERE f.filename NOT LIKE \'.\'
                    AND (c.path LIKE :path OR c.id = :cid) ' . $sqlwhere;

        $sqlselectfiles = 'SELECT f.*, c.contextlevel, c.instanceid,' . $usernamefields .
        ' ' . $sql . ' ORDER BY f.component, f.filename';

        $params = array(
            'path' => $this->context->path . '/%',
            'cid' => $this->context->id,
            'component' => $sqlwherecomponent,
        );

        $this->filelist = $DB->get_records_sql($sqlselectfiles, $params, $offset, $limit);

        // Determine number of all files.
        if (count($this->filelist) < $limit) {
            $this->filescount = count($this->filelist) + $offset;
        } else {
            $sqlcount = 'SELECT COUNT(*) ' . $sql;
            $this->filescount = $DB->count_records_sql($sqlcount, $params);
        }

        return $this->filelist;
    }

    /**
     * Creates an SQL snippet
     *
     * @param array $types
     * @param boolean $in
     * @return string
     */
    protected function get_sql_mimetype($types, $in) {
        if (is_array($types)) {
            $list = array();
            foreach ($types as $type) {
                $list = array_merge($list, self::$mimetypes[$type]);
            }
        } else {
            $list = &self::$mimetypes[$types];
        }

        if ($in) {
            $first = "(f.mimetype LIKE '";
            $glue = "' OR f.mimetype LIKE '";
        } else {
            $first = "(f.mimetype NOT LIKE '";
            $glue = "' AND f.mimetype NOT LIKE '";
        }

        return $first . implode($glue, $list) . "')";
    }

    /**
     * Returns the number of files in a component and with a specific file type.
     * May only be called after get_file_list.
     */
    public function get_file_list_total_size() {
        return $this->filescount;
    }

    /**
     * Get all available components with files.
     * @return array
     */
    public function get_components() {
        global $DB;

        if ($this->components !== null) {
            return $this->components;
        }

        $sql = 'SELECT f.component
                FROM {files} f
                LEFT JOIN {context} c ON (c.id = f.contextid)
                WHERE f.filename NOT LIKE \'.\'
                    AND (c.path LIKE :path OR c.id = :cid)
                GROUP BY f.component';

        $params = array('path' => $this->context->path . '/%', 'cid' => $this->context->id);
        $ret = $DB->get_fieldset_sql($sql, $params);

        $this->components = array();
        foreach ($ret as $r) {
            $this->components[$r] = local_listcoursefiles_get_component_translation($r);
        }

        asort($this->components, SORT_STRING | SORT_FLAG_CASE);
        $componentsall = array(
            'all' => \get_string('all_files', 'local_listcoursefiles'),
            'all_wo_submissions' => \get_string('all_wo_submissions', 'local_listcoursefiles'),
        );
        $this->components = $componentsall + $this->components;

        return $this->components;
    }

    /**
     * Remember licences as array
     *
     * @return array|null
     * @throws \coding_exception
     */
    public static function get_available_licenses() {
        global $CFG;

        if (self::$licenses === null) {
            self::$licenses = array();
            $a = explode(',', $CFG->licenses);
            foreach ($a as $license) {
                self::$licenses[$license] = \get_string($license, 'license');
            }
        }

        return self::$licenses;
    }

    /**
     * Wraps license name in span element with background color as per plugin settings or
     * retuns license name if no color set
     *
     * @param string $licenseshort short name of a license
     * @return string full name of the license with HTML
     * @throws dml_exception|coding_exception
     */
    public static function get_license_name_color($licenseshort) {
        if (self::$licenscolors === null) {
            self::get_available_licenses();
            $colorscfg = get_config('local_listcoursefiles', 'licensecolors');
            $matches = array();
            preg_match_all('@\s*(\S+)\s*([a-fA-F0-9]{6})\s*@', $colorscfg, $matches, PREG_SET_ORDER);
            self::$licenscolors = array();
            foreach ($matches as $m) {
                self::$licenscolors[$m[1]] = $m[2];
            }
        }

        $name = (isset(self::$licenses[$licenseshort])) ? self::$licenses[$licenseshort] : '';
        if (isset(self::$licenscolors[$licenseshort])) {
            $name = \html_writer::tag('span', $name, array('style' => 'background-color: #' . self::$licenscolors[$licenseshort]));
        }
        return $name;
    }

    /**
     * Change the license of multiple files.
     *
     * @param array $fileids keys are the file IDs
     * @param string $license shortname of the license
     * @throws moodle_exception
     */
    public function set_files_license($fileids, $license) {
        global $DB;

        $licenses = self::get_available_licenses();
        if (!isset($licenses[$license])) {
            throw new \moodle_exception('invalid_license', 'local_listcoursefiles');
        }

        if (count($fileids) > LOCAL_LISTCOURSEFILES_MAX_FILES) {
            throw new \moodle_exception('too_many_files', 'local_listcoursefiles');
        }

        if (count($fileids) == 0) {
            return;
        }

        // Check if the given files really belong to the context.
        list($sqlin, $paramfids) = $DB->get_in_or_equal(array_keys($fileids), SQL_PARAMS_QM);
        $sql = 'SELECT f.id, f.contextid, c.path
                FROM {files} f
                JOIN {context} c ON (c.id = f.contextid)
                WHERE f.id ' . $sqlin;
        $res = $DB->get_records_sql($sql, $paramfids);

        $checkedfileids = $this->check_files_context($res, true);
        if (count($checkedfileids) == 0) {
            return;
        }

        list($sqlin, $paramfids) = $DB->get_in_or_equal($checkedfileids, SQL_PARAMS_QM);
        $transaction = $DB->start_delegated_transaction();
        $sql = 'UPDATE {files}
                SET license = ?
                WHERE id ' . $sqlin;
        $DB->execute($sql, array_merge(array($license), $paramfids));

        foreach ($checkedfileids as $fid) {
            $event = event\license_changed::create(array(
                'context' => $this->context,
                'objectid' => $fid,
                'other' => array('license' => $license),
            ));
            $event->trigger();
        }
        $transaction->allow_commit();
    }

    /**
     * Check given files whether they belong to the context.
     *
     * The file objects need to have the contextid and the context path.
     *
     * @param array $files array of stdClass as retrieved from the files and context table
     * @param bool $returnfileids return file ids or objects
     * @return array file ids that belong to the context
     */
    protected function check_files_context(&$files, $returnfileids = false) {
        $thiscontextpath = $this->context->path . '/';
        $thiscontextpathlen = strlen($thiscontextpath);
        $thiscontextid = $this->context->id;
        $checkedfiles = array();
        foreach ($files as &$f) {
            if ($f->contextid == $thiscontextid || substr($f->path, 0, $thiscontextpathlen) === $thiscontextpath) {
                $checkedfiles[] = ($returnfileids) ? $f->id : $f;
            }
        }

        return $checkedfiles;
    }

    /**
     * Download a zip file of the files with the given ids.
     *
     * This function does not return if the zip archive could be created.
     *
     * @param array $fileids file ids
     * @throws moodle_exception
     */
    public function download_files(&$fileids) {
        global $DB, $CFG;

        if (count($fileids) > LOCAL_LISTCOURSEFILES_MAX_FILES) {
            throw new \moodle_exception('too_many_files', 'local_listcoursefiles');
        }

        if (count($fileids) == 0) {
            throw new \moodle_exception('no_file_selected', 'local_listcoursefiles');
        }

        list($sqlin, $paramfids) = $DB->get_in_or_equal(array_keys($fileids), SQL_PARAMS_QM);
        $sql = 'SELECT f.*, c.path, r.repositoryid, r.reference, r.lastsync AS referencelastsync
                FROM {files} f
                LEFT JOIN {context} c ON (c.id = f.contextid)
                LEFT JOIN {files_reference} r ON (f.referencefileid = r.id)
                WHERE f.id ' . $sqlin;
        $res = $DB->get_records_sql($sql, $paramfids);

        $checkedfiles = $this->check_files_context($res);
        $fs = get_file_storage();
        $filesforzipping = array();
        foreach ($checkedfiles as $file) {
            $fname = $this->download_get_unique_file_name($file->filename, $filesforzipping);
            $filesforzipping[$fname] = $fs->get_file_instance($file);
        }

        $filename = clean_filename($this->coursemodinfo->get_course()->fullname . '.zip');
        $tmpfile = tempnam($CFG->tempdir . '/', 'local_listcoursefiles');
        $zip = new \zip_packer();
        if ($zip->archive_to_pathname($filesforzipping, $tmpfile)) {
            send_temp_file($tmpfile, $filename);
        }
    }

    /**
     * Generate a unique file name for storage.
     *
     * If a file does already exist with $filename in $existingfiles as key,
     * a number in parentheses is appended to the file name.
     *
     * @param string $filename
     * @param array $existingfiles
     * @return string unique file name
     */
    protected function download_get_unique_file_name($filename, &$existingfiles) {
        $name = clean_filename($filename);

        $lastdot = strrpos($name, '.');
        if ($lastdot === false) {
            $filename = $name;
            $extension = '';
        } else {
            $filename = substr($name, 0, $lastdot);
            $extension = substr($name, $lastdot);
        }

        $i = 1;
        while (isset($existingfiles[$name])) {
            $name = $filename . '(' . $i++ . ')' . $extension;
        }

        return $name;
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
                return new \moodle_url('/pluginfile.php/'. $file->contextid . '/' . $file->component . '/' .
                        $file->filearea . '/0' . $file->filepath . $file->filename);

            case 'mod_assign#intro':
            case 'mod_label#intro':
                return new \moodle_url('/pluginfile.php/'. $file->contextid . '/' . $file->component . '/' .
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
                return new \moodle_url('/pluginfile.php/'. $file->contextid . '/' . $file->component . '/' .
                        $file->filearea . '/' . $file->itemid . $file->filepath . $file->filename);

            case 'course#legacy':
                return new \moodle_url('/file.php/'. $this->courseid . $file->filepath . $file->filename);
        }

        return null;
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
     * Collate an array of available file types
     *
     * @return array
     * @throws \coding_exception
     */
    public static function get_file_types() {
        $types = array('all' => \get_string('filetype_all', 'local_listcoursefiles'));
        foreach (self::$mimetypes as $type => $unused) {
            $types[$type] = \get_string('filetype_' . $type, 'local_listcoursefiles');
        }
        $types['other'] = \get_string('filetype_other', 'local_listcoursefiles');
        return $types;
    }

    /**
     * Try to get the name of the file type in the user's lang
     *
     * @param string $mimetype
     * @return \lang_string|string
     * @throws \coding_exception
     */
    public static function get_file_type_translation($mimetype) {
        foreach (self::$mimetypes as $name => $types) {
            foreach ($types as $mime) {
                if ($mime === $mimetype ||
                        (substr($mime, -1) === '%' && strncmp($mime, $mimetype, strlen($mime) - 1) === 0)) {
                    return \get_string('filetype_' . $name, 'local_listcoursefiles');
                }
            }
        }

        return $mimetype;
    }

    /**
     * Check if the predefined list of mimetypes should be overridden.
     */
    public static function check_config_mimetypes() {
        global $CFG;

        if (isset($CFG->filemimetypes)) {
            self::$mimetypes = $CFG->filemimetypes;
        }
    }

    /**
     * Checks if embedded files have been used
     *
     * @param object $file
     * @param integer $courseid
     * @return bool
     * @throws \dml_exception
     */
    public function get_file_use($file, $courseid) {
        global $DB;
        $isused = false;

        switch ($file->contextlevel){
            case '50' : // Course.
                if ($file->component === 'contentbank') {
                    $isused = null;
                } else if ($file->filearea === 'section') {
                    if ($section = $DB->get_record('course_sections', ['id' => $file->itemid])) {
                        if (false !== strpos($section->summary, '@@PLUGINFILE@@/' . rawurlencode($file->filename))) {
                            $isused = true;
                        }
                    }
                } else if ($file->filearea === 'overviewfiles') {
                    $isused = true;
                } else if ($file->filearea === 'summary') {
                    $course = $DB->get_record('course', ['id' => $courseid]);
                    if (false !== strpos($course->summary, '@@PLUGINFILE@@/' . rawurlencode($file->filename))) {
                        $isused = true;
                    }
                }
                break;
            case '70' : // Course module.
                $modname = str_replace('mod_', '', $file->component);
                if ($file->filearea === 'intro') {
                    $sql = 'SELECT l.* FROM {context} ctx
                            JOIN {course_modules} cm ON cm.id = ctx.instanceid
                            JOIN {' . $modname . '} l ON l.id = cm.instance
                            WHERE ctx.id = ?';
                    if ($mod = $DB->get_record_sql($sql, [$file->contextid])) {
                        if (false !== strpos($mod->intro, '@@PLUGINFILE@@/' . rawurlencode($file->filename))) {
                            $isused = true;
                        }
                    }
                } else {
                    $fn = 'get_file_use_' . $modname;
                    if (is_callable(array($this, $fn))) {
                        $isused = call_user_func(array($this, $fn), $file);
                    } else {
                        $isused = null;
                    }
                }
                break;
            default :
                $isused = null;
        }
        return $isused;
    }

    /**
     * @param object $file
     * @return bool | null
     */
    private function get_file_use_assign($file) {
        // File areas = intro, introattachment.
        if ($file->filearea === 'introattachment') {
            return true;
        }
    }

    /**
     * @param object $file
     * @return bool | null
     * @throws \dml_exception
     */
    private function get_file_use_book($file) {
        // File areas = intro, chapter.
        global $DB;
        if ($file->filearea === 'chapter') {
            $chapter = $DB->get_record('book_chapters', ['id' => $file->itemid]);
            if (false !== strpos($chapter->content, '@@PLUGINFILE@@/' . rawurlencode($file->filename))) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * @param object $file
     * @return bool | null
     * @throws \dml_exception
     */
    private function get_file_use_data($file) {
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
     * @param object $file
     * @return bool | null
     * @throws \dml_exception
     */
    private function get_file_use_feedback($file) {
        // File areas = intro, item, page_after_submit.
        global $DB;
        if ($file->filearea === 'item') {
            $item = $DB->get_record('feedback_item', ['id' => $file->itemid]);
            if (false !== strpos($item->presentation, '@@PLUGINFILE@@/' . rawurlencode($file->filename))) {
                return true;
            } else {
                return false;
            }
        } else if ($file->filearea = 'page_after_submit') {
            $sql = 'SELECT f.* FROM {feedback} f
                    JOIN {course_modules} cm ON cm.instance = f.id
                    JOIN {context} ctx ON ctx.instanceid = cm.id
                    WHERE ctx.id = ?';
            $feedback = $DB->get_record_sql($sql, [$file->contextid]);
            if (false !== strpos($feedback->page_after_submit, '@@PLUGINFILE@@/' . rawurlencode($file->filename))) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * @param $file
     * @return bool
     */
    private function get_file_use_folder($file) {
        // File areas = intro, content.
        return true;
    }

    /**
     * @param object $file
     * @return bool | null
     * @throws \dml_exception
     */
    private function get_file_use_forum($file) {
        // File areas = intro, post.
        global $DB;
        if ($file->filearea === 'post') {
            $post = $DB->get_record('forum_posts', ['id' => $file->itemid]);
            if (false !== strpos($post->message, '@@PLUGINFILE@@/' . rawurlencode($file->filename))) {
                return true;
            } else {
                return false;
            }
        } else if ($file->filearea === 'attachment') {
            return true;
        }
    }

    /**
     * @param object $file
     * @return bool
     */
    private function get_file_use_h5pactivity($file) {
        // File areas = intro, package.
        return true;
    }

    /**
     * @param object file
     * @return bool | null
     * @throws \dml_exception
     */
    private function get_file_use_page($file) {
        // File areas = intro, content.
        global $DB;
        if ($file->filearea === 'content') {
            $sql = 'SELECT p.* FROM {page} p
                    JOIN {course_modules} cm ON cm.instance = p.id
                    JOIN {context} ctx ON ctx.instanceid = cm.id
                    WHERE ctx.id = ?';
            $page = $DB->get_record_sql($sql, [$file->contextid]);
            if (false !== strpos($page->content, '@@PLUGINFILE@@/' . rawurlencode($file->filename))) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * @param object $file
     * @return bool
     */
    private function get_file_use_resource($file) {
        // File areas = intro, content.
        return true;
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
        switch ($file->contextlevel){
            case '50' :
                if ($file->filearea === 'section') {
                    $url = new \moodle_url('/course/editsection.php?', ['id' => $file->itemid]);
                } else if ($file->filearea === 'overviewfiles' || $file->filearea === 'summary') {
                    $url = new \moodle_url('/course/edit.php?', ['id' => $this->courseid]);
                }
                break;
            case '70' :
                if ($file->filearea !== 'intro') { // Just checking description for now.
                    break;
                }
                $sql = 'SELECT cm.* FROM {context} ctx
                        JOIN {course_modules} cm ON cm.id = ctx.instanceid
                        WHERE ctx.id = ?';
                $mod = $DB->get_record_sql($sql, [$file->contextid]);
                $url = new \moodle_url('/course/modedit.php?', ['update' => $mod->id]);
                break;
            default :
                $isused = null;
        }
        return $url;
    }
}
