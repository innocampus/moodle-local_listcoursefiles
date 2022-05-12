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
     * @var \context
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
     * @var string
     */
    protected $filtercomponent;

    /**
     * @var string
     */
    protected $filterfiletype;

    /**
     * @var \course_modinfo
     */
    protected $coursemodinfo;

    /**
     * @var int
     */
    protected $courseid;

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
            $sqlwhere .= ' AND ' . $this->get_sql_mimetype(array_keys(mimetypes::get_mime_types()), false);
        } else if (isset(mimetypes::get_mime_types()[$this->filterfiletype])) {
            $sqlwhere .= ' AND ' . $this->get_sql_mimetype($this->filterfiletype, true);
        }

        $usernameselect = implode(', ', array_map(function($field) {
            return 'u.' . $field;
        }, \core_user\fields::get_name_fields()));

        $sql = 'FROM {files} f
                LEFT JOIN {context} c ON (c.id = f.contextid)
                LEFT JOIN {user} u ON (u.id = f.userid)
                WHERE f.filename NOT LIKE \'.\'
                    AND (c.path LIKE :path OR c.id = :cid) ' . $sqlwhere;

        $sqlselectfiles = 'SELECT f.*, c.contextlevel, c.instanceid, ' . $usernameselect .
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
     * @param mixed $types
     * @param boolean $in
     * @return string
     */
    protected function get_sql_mimetype($types, $in) {
        if (is_array($types)) {
            $list = array();
            foreach ($types as $type) {
                $list = array_merge($list, mimetypes::get_mime_types()[$type]);
            }
        } else {
            $list = &mimetypes::get_mime_types()[$types];
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
     * Change the license of multiple files.
     *
     * @param array $fileids keys are the file IDs
     * @param string $license shortname of the license
     * @throws \moodle_exception
     */
    public function set_files_license($fileids, $license) {
        global $DB;

        $licenses = licences::get_available_licenses();
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
     * @throws \moodle_exception
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
     * Collate an array of available file types
     *
     * @return array
     * @throws \coding_exception
     */
    public static function get_file_types() {
        $types = array('all' => \get_string('filetype_all', 'local_listcoursefiles'));
        foreach (mimetypes::get_mime_types() as $type => $unused) {
            $types[$type] = \get_string('filetype_' . $type, 'local_listcoursefiles');
        }
        $types['other'] = \get_string('filetype_other', 'local_listcoursefiles');
        return $types;
    }

}
