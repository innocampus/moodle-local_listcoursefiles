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
 * @package   local_listcoursefiles
 * @copyright 2017 Martin Gauk (@innoCampus, TU Berlin)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_listcoursefiles;

use core\exception\coding_exception;
use core\context\course as context_course;
use core\exception\moodle_exception;
use core_user\fields as user_fields;
use course_modinfo;
use dml_exception;
use stdClass;
use zip_packer;

/**
 * Manages files uploaded to a course.
 *
 * @package   local_listcoursefiles
 * @copyright 2017 Martin Gauk (@innoCampus, TU Berlin)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_files {
    /** @var int Maximum number of files per page. */
    public const MAX_FILES = 500;

    /** @var context_course Context of the course for which the files are managed. */
    public readonly context_course $context;

    /** @var course_modinfo Module info of the course for which the files are managed. */
    public readonly course_modinfo $coursemodinfo;

    /**
     * @var string[]|null Cached translated names of all components with files in the course, indexed by component name;
     *                    `null` if not yet fetched.
     */
    private array|null $components = null;

    /** @var course_file[]|null Cached array of files; `null` if not yet fetched. */
    private array|null $filelist = null;

    /** @var int|null Cached total number of files; `null` if not yet counted. */
    private int|null $filescount = null;

    /**
     * Initializes with the given course, component, and type filters; sets the context and module info of the course.
     *
     * @param int $courseid ID of the course for which to manage the files.
     * @param string $component Name of the component by which to filter the files.
     * @param file_type|null $filetype Type of the files by which to filter.
     * @param int $offset Offset of the first file to return (for pagination purposes).
     * @param int $limit Maximum number of files to return (for pagination purposes).
     * @throws moodle_exception No course found with the given ID.
     */
    public function __construct(
        /** @var int ID of the course for which the files are managed. */
        public readonly int $courseid,
        /** @var string Name of the component by which the files are filtered. */
        public readonly string $component,
        /** @var file_type|null Type by which the files are filtered. */
        public readonly file_type|null $filetype = null,
        /** @var int Offset of the first file to be returned (for pagination purposes). */
        public readonly int $offset = 0,
        /** @var int Maximum number of files that will be returned (for pagination purposes). */
        public readonly int $limit = self::MAX_FILES,
    ) {
        $this->context = context_course::instance($courseid);
        $this->coursemodinfo = get_fast_modinfo($courseid);
    }

    /**
     * Retrieves the specified range of files within the course, matching the component and filetype filters.
     *
     * @return course_file[] Records representing the course files.
     * @throws coding_exception
     * @throws dml_exception
     */
    public function fetch(): array {
        global $DB;
        if (!is_null($this->filelist)) {
            return $this->filelist;
        }
        $usernamefields = implode(', ', array_map(fn (string $field): string => "u.$field", user_fields::get_name_fields()));
        [$sqlwhere, $params] = $this->get_sql_filters();
        $sql = "SELECT f.*, c.contextlevel, c.instanceid, $usernamefields
                  FROM {files} f
             LEFT JOIN {context} c ON (c.id = f.contextid)
             LEFT JOIN {user} u ON (u.id = f.userid)
                 WHERE f.filename NOT LIKE '.'
                       AND (c.path LIKE :path OR c.id = :cid)
                       $sqlwhere
              ORDER BY f.component, f.filename";
        $params += [
            'path' => "{$this->context->path}/%",
            'cid' => $this->context->id,
        ];
        $records = $DB->get_records_sql($sql, $params, $this->offset, $this->limit);
        $this->filelist = array_map(
            fn (stdClass $record): course_file => course_file::from_record($record, $this->courseid),
            $records,
        );
        return $this->filelist;
    }

    /**
     * Returns the total number of files in the course, matching the component and filetype filters.
     *
     * @return int Files count.
     * @throws coding_exception
     * @throws dml_exception
     */
    public function count(): int {
        global $DB;
        if (!is_null($this->filescount)) {
            return $this->filescount;
        }
        if (!is_null($this->filelist) && count($this->filelist) < $this->limit) {
            $this->filescount = count($this->filelist) + $this->offset;
            return $this->filescount;
        }
        [$sqlwhere, $params] = $this->get_sql_filters();
        $sql = "SELECT COUNT(*)
                  FROM {files} f
             LEFT JOIN {context} c ON (c.id = f.contextid)
                 WHERE f.filename NOT LIKE '.'
                       AND (c.path LIKE :path OR c.id = :cid)
                       $sqlwhere";
        $params += [
            'path' => "{$this->context->path}/%",
            'cid' => $this->context->id,
        ];
        $this->filescount = $DB->count_records_sql($sql, $params);
        return $this->filescount;
    }

    /**
     * Returns the SQL fragment for the component and filetype filters.
     *
     * @return array{string, array<string, string>} SQL snippet and parameters.
     * @throws coding_exception
     * @throws dml_exception
     */
    private function get_sql_filters(): array {
        [$sqlwhere, $params] = ['', []];
        [$filtersql, $filterparams] = $this->get_sql_component_filter();
        if ($filtersql !== '') {
            $sqlwhere = "AND $filtersql";
            $params += $filterparams;
        }
        [$filtersql, $filterparams] = $this->get_sql_mimetype_filter();
        if ($filtersql !== '') {
            $sqlwhere = "AND ($filtersql)";
            $params += $filterparams;
        }
        return [$sqlwhere, $params];
    }

    /**
     * Returns an SQL fragment for the component filter.
     *
     * @return array{string, array<string, string>} SQL snippet and parameters.
     * @throws coding_exception
     * @throws dml_exception
     */
    private function get_sql_component_filter(): array {
        if ($this->component === 'all_wo_submissions') {
            return ["f.component NOT LIKE :component", ['component' => 'assign%']];
        }
        if ($this->component !== 'all' && isset($this->get_components()[$this->component])) {
            return ["f.component LIKE :component", ['component' => $this->component]];
        }
        // TODO: Throw an exception, if the component is unknown?
        return ['', []];
    }

    /**
     * Returns an SQL fragment for the mimetype filter.
     *
     * @return array{string, array<string, string>} SQL snippet and parameters.
     */
    private function get_sql_mimetype_filter(): array {
        if (is_null($this->filetype)) {
            return ['', []];
        }
        if ($this->filetype === file_type::OTHER) {
            // Construct an SQL fragment that matches all MIME types that are _not_ in the list of known MIME types.
            $mimetypes = file_type::iter_all_mime_types();
            [$oplike, $opequal] = ['NOT LIKE', '<>'];
            $glue = ' AND ';
        } else {
            // Construct an SQL fragment that matches _any_ of the MIME types associated with the given file type.
            // If the file type is not known, the expression will be empty and thus no filter will be applied.
            $mimetypes = $this->filetype->get_mime_types();
            [$oplike, $opequal] = ['LIKE', '='];
            $glue = ' OR ';
        }
        $conditions = [];
        $params = [];
        foreach ($mimetypes as $i => $pattern) {
            $op = str_ends_with($pattern, '%') ? $oplike : $opequal;
            $conditions[] = "f.mimetype $op :mimetype$i";
            $params["mimetype$i"] = $pattern;
        }
        $sql = implode($glue, $conditions);
        return [$sql, $params];
    }

    /**
     * Returns all available components that have files in the course.
     *
     * @return string[] Array of translated names of all components with files in the course, indexed by component name.
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_components(): array {
        global $DB;
        if ($this->components !== null) {
            return $this->components;
        }
        $sql = "SELECT f.component
                  FROM {files} f
             LEFT JOIN {context} c ON (c.id = f.contextid)
                 WHERE f.filename NOT LIKE '.'
                       AND (c.path LIKE :path OR c.id = :cid)
              GROUP BY f.component";
        $params = ['path' => "{$this->context->path}/%", 'cid' => $this->context->id];
        $this->components = [];
        foreach ($DB->get_fieldset_sql($sql, $params) as $name) {
            $this->components[$name] = self::get_component_display_name($name);
        }
        asort($this->components, SORT_STRING | SORT_FLAG_CASE);
        $this->components = [
            'all' => get_string('all_files', 'local_listcoursefiles'),
            'all_wo_submissions' => get_string('all_wo_submissions', 'local_listcoursefiles'),
        ] + $this->components;
        return $this->components;
    }

    /**
     * Changes the license for the specified files.
     *
     * @param string $license Short name of the license to set for the specified files.
     * @param int $fileid ID of the file for which to modify the license.
     * @param int ...$fileids More IDs of files for which to modify the license.
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function set_files_license(string $license, int $fileid, int ...$fileids): void {
        global $DB;
        $fileids = array_merge([$fileid], $fileids);
        $licenses = licences::get_available_licenses();
        if (!isset($licenses[$license])) {
            throw new moodle_exception('invalid_license', 'local_listcoursefiles');
        }
        if (count($fileids) > self::MAX_FILES) {
            throw new moodle_exception('too_many_files', 'local_listcoursefiles');
        }
        // Only fetch records for those files that really belong to the context. Exclude the top file area folders ('.' file names).
        [$sqlin, $params] = $DB->get_in_or_equal($fileids, SQL_PARAMS_NAMED, 'fileid');
        $sql = "SELECT f.*, c.contextlevel, c.instanceid
                  FROM {files} f
                  JOIN {context} c ON (c.id = f.contextid)
                 WHERE f.filename NOT LIKE '.'
                       AND (c.path LIKE :path OR c.id = :cid)
                       AND f.id $sqlin";
        $params += [
            'path' => "{$this->context->path}/%",
            'cid' => $this->context->id,
        ];
        $records = $DB->get_records_sql($sql, $params);
        if (count($records) === 0) {
            return;
        }
        $files = array_map(
            fn (stdClass $record): course_file => course_file::from_record($record, $this->courseid),
            $records,
        );
        [$sqlin, $params] = $DB->get_in_or_equal(array_keys($files), SQL_PARAMS_NAMED, 'fileid');
        $transaction = $DB->start_delegated_transaction();
        $sql = "UPDATE {files} SET license = :license WHERE id $sqlin";
        $DB->execute($sql, $params + ['license' => $license]);
        foreach ($files as $file) {
            event\license_changed::instance($this->context, $file, $license)->trigger();
        }
        $transaction->allow_commit();
    }

    /**
     * Downloads a zip file of the files with the given IDs.
     *
     * This function does not return if the zip archive could be created.
     *
     * @param int $fileid ID of the file to download.
     * @param int ...$fileids More IDs of files to download.
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function download_files(int $fileid, int ...$fileids): void {
        global $CFG, $DB;
        $fileids = array_merge([$fileid], $fileids);
        if (count($fileids) > self::MAX_FILES) {
            throw new moodle_exception('too_many_files', 'local_listcoursefiles');
        }
        // Only fetch records for those files that really belong to the context. Exclude the top file area folders ('.' file names).
        [$sqlin, $params] = $DB->get_in_or_equal($fileids, SQL_PARAMS_NAMED, 'fileid');
        $sql = "SELECT f.*, r.repositoryid, r.reference, r.lastsync AS referencelastsync
                  FROM {files} f
             LEFT JOIN {context} c ON (c.id = f.contextid)
             LEFT JOIN {files_reference} r ON (f.referencefileid = r.id)
                 WHERE f.filename NOT LIKE '.'
                       AND (c.path LIKE :path OR c.id = :cid)
                       AND f.id $sqlin";
        $params += [
            'path' => "{$this->context->path}/%",
            'cid' => $this->context->id,
        ];
        $records = $DB->get_records_sql($sql, $params);
        if (count($records) === 0) {
            return;
        }
        $fs = get_file_storage();
        $files = [];
        foreach ($records as $record) {
            $filename = self::download_get_unique_file_name($record->filename, $files);
            $files[$filename] = $fs->get_file_instance($record);
        }
        $zipname = clean_filename("{$this->coursemodinfo->get_course()->fullname}.zip");
        $tmpfile = tempnam("$CFG->tempdir/", 'local_listcoursefiles');
        $zip = new zip_packer();
        // TODO: Throw an exception if the zip archive could not be created?
        if ($zip->archive_to_pathname($files, $tmpfile)) {
            send_temp_file($tmpfile, $zipname);
        }
    }

    /**
     * Generates a unique file name for the download of multiple files.
     *
     * If a key like the given `$filename` already exists in `$existingfiles`, a number in parentheses is appended to the file name.
     *
     * @param string $filename File name to use.
     * @param array $existingfiles Array with file name keys to exclude.
     * @return string Unique file name.
     */
    private static function download_get_unique_file_name(string $filename, array $existingfiles): string {
        $name = clean_filename($filename);
        if (($lastdot = strrpos($name, '.')) === false) {
            $filename = $name;
            $extension = '';
        } else {
            $filename = substr($name, 0, $lastdot);
            $extension = substr($name, $lastdot);
        }
        $i = 0;
        while (isset($existingfiles[$name])) {
            ++$i;
            $name = "$filename($i)$extension";
        }
        return $name;
    }

    /**
     * Returns the human-readable name (translated) of the given component if possible.
     *
     * @param string $name Name of the component.
     * @return string Component display name.
     * @throws coding_exception
     */
    public static function get_component_display_name(string $name): string {
        if (get_string_manager()->string_exists('pluginname', $name)) {
            return get_string('pluginname', $name);
        } else if (get_string_manager()->string_exists($name, '')) {
            return get_string($name);
        }
        return $name;
    }
}
