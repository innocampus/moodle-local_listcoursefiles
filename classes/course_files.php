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

use core\context\course as context_course;
use core\exception\coding_exception;
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
     * @param filetype $filetype Type of the files by which to filter.
     * @param int $offset Offset of the first file to return (for pagination purposes).
     * @param int $limit Maximum number of files to return (for pagination purposes).
     * @throws moodle_exception No course found with the given ID.
     */
    public function __construct(
        /** @var int ID of the course for which the files are managed. */
        public readonly int $courseid,
        /** @var string Name of the component by which the files are filtered. */
        public readonly string $component,
        /** @var filetype Type by which the files are filtered. */
        public readonly filetype $filetype = filetype::all,
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
        [$ctxwhere, $ctxparams] = $this->sql_filter_course_context();
        [$cmpwhere, $cmpparams] = $this->sql_filter_component();
        [$mimwhere, $mimparams] = $this->sql_filter_mimetype();
        $where = implode(' AND ', array_filter([$ctxwhere, $cmpwhere, $mimwhere]));
        $params = $ctxparams + $cmpparams + $mimparams;
        $sql = "SELECT f.*, c.contextlevel, c.instanceid, $usernamefields
                  FROM {files} f
             LEFT JOIN {context} c ON (c.id = f.contextid)
             LEFT JOIN {user} u ON (u.id = f.userid)
                 WHERE $where
              ORDER BY f.component, f.filename";
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
        [$ctxwhere, $ctxparams] = $this->sql_filter_course_context();
        [$cmpwhere, $cmpparams] = $this->sql_filter_component();
        [$mimwhere, $mimparams] = $this->sql_filter_mimetype();
        $where = implode(' AND ', array_filter([$ctxwhere, $cmpwhere, $mimwhere]));
        $params = $ctxparams + $cmpparams + $mimparams;
        $sql = "SELECT COUNT(*)
                  FROM {files} f
             LEFT JOIN {context} c ON (c.id = f.contextid)
                 WHERE $where";
        $this->filescount = $DB->count_records_sql($sql, $params);
        return $this->filescount;
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
        [$where, $params] = $this->sql_filter_course_context();
        $sql = "SELECT f.component
                  FROM {files} f
             LEFT JOIN {context} c ON (c.id = f.contextid)
                 WHERE $where
              GROUP BY f.component";
        $this->components = [];
        foreach ($DB->get_fieldset_sql($sql, $params) as $name) {
            $this->components[$name] = self::get_component_display_name($name);
        }
        asort($this->components, SORT_STRING | SORT_FLAG_CASE);
        $this->components = [
            'all' => get_string('all_files', 'local_listcoursefiles'),
            'all_without_submissions' => get_string('all_without_submissions', 'local_listcoursefiles'),
        ] + $this->components;
        return $this->components;
    }

    /**
     * Changes the license for the specified files.
     *
     * @param string $shortname Short name of the license to set for the specified files
     * @param int ...$fileids IDs of files for which to modify the license.
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function set_license(string $shortname, int ...$fileids): void {
        global $DB;
        if (!isset(licenses::get_available_licenses()[$shortname])) {
            throw new moodle_exception('error:invalid_license', 'local_listcoursefiles');
        }
        self::validate_file_ids($fileids);
        [$where, $params] = $this->sql_filter_selected_files($fileids);
        $sql = "SELECT f.*, c.contextlevel, c.instanceid
                  FROM {files} f
                  JOIN {context} c ON (c.id = f.contextid)
                 WHERE $where";
        $records = $DB->get_records_sql($sql, $params);
        // Silently return if validated IDs map to no rows here: legitimate "nothing to do" and out-of-context IDs both land here.
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
        $DB->execute($sql, $params + ['license' => $shortname]);
        foreach ($files as $file) {
            event\license_changed::instance($this->context, $file, $shortname)->trigger();
        }
        $transaction->allow_commit();
    }

    /**
     * Downloads a zip file of the files with the given IDs.
     *
     * This function does not return if the zip archive could be created.
     *
     * @param int ...$fileids IDs of files to download.
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function download(int ...$fileids): void {
        global $CFG, $DB;
        self::validate_file_ids($fileids);
        [$where, $params] = $this->sql_filter_selected_files($fileids);
        $sql = "SELECT f.*, r.repositoryid, r.reference, r.lastsync AS referencelastsync
                  FROM {files} f
             LEFT JOIN {context} c ON (c.id = f.contextid)
             LEFT JOIN {files_reference} r ON (f.referencefileid = r.id)
                 WHERE $where";
        $records = $DB->get_records_sql($sql, $params);
        // Silently return if validated IDs map to no rows here: legitimate "nothing to do" and out-of-context IDs both land here.
        if (count($records) === 0) {
            return;
        }
        $fs = get_file_storage();
        $files = [];
        foreach ($records as $record) {
            $filename = self::get_unique_download_name($record->filename, $files);
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

    /**
     * Ensures the given file ID array is non-empty and does not exceed {@see self::MAX_FILES}.
     *
     * @param int[] $fileids Array of file IDs to validate.
     * @throws moodle_exception
     */
    private static function validate_file_ids(array $fileids): void {
        if (empty($fileids)) {
            throw new moodle_exception('error:no_files_selected', 'local_listcoursefiles');
        }
        if (count($fileids) > self::MAX_FILES) {
            throw new moodle_exception('error:too_many_files', 'local_listcoursefiles');
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
    private static function get_unique_download_name(string $filename, array $existingfiles): string {
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
     * Returns the SQL `WHERE` fragment and parameters restricting files to the course context and excluding directory-entry rows.
     *
     * Relies on the tables `{files} f` joined against `{context} c`.
     *
     * @return array{string, array<string, mixed>} `WHERE` fragment and named parameters.
     */
    private function sql_filter_course_context(): array {
        return [
            "f.filename NOT LIKE '.' AND (c.path LIKE :path OR c.id = :cid)",
            [
                'path' => "{$this->context->path}/%",
                'cid' => $this->context->id,
            ],
        ];
    }

    /**
     * Returns the SQL `WHERE` fragment and parameters for the component filter.
     *
     * Returns an empty fragment when the filter is `all`.
     *
     * Relies on the table `{files} f`.
     *
     * @return array{string, array<string, string>} `WHERE` fragment and named parameters.
     * @throws coding_exception
     * @throws dml_exception
     */
    private function sql_filter_component(): array {
        if ($this->component === 'all_without_submissions') {
            return ["f.component NOT LIKE :component", ['component' => 'assign%']];
        }
        if ($this->component !== 'all' && isset($this->get_components()[$this->component])) {
            return ["f.component LIKE :component", ['component' => $this->component]];
        }
        // TODO: Throw an exception, if the component is unknown?
        return ['', []];
    }

    /**
     * Returns the SQL `WHERE` fragment and parameters for the MIME type filter.
     *
     * Returns an empty fragment when the filter is {@see filetype::all}. The non-empty fragment is wrapped in parentheses
     * because it contains internal `OR`/`AND` connectives and must compose safely with outer `AND`s.
     *
     * Relies on the table `{files} f`.
     *
     * @return array{string, array<string, string>} `WHERE` fragment and named parameters.
     */
    private function sql_filter_mimetype(): array {
        if ($this->filetype === filetype::all) {
            return ['', []];
        }
        if ($this->filetype === filetype::other) {
            // Construct an SQL fragment that matches all MIME types that are _not_ in the list of known MIME types.
            $mimetypes = filetype::all->get_mime_types();
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
        return ['(' . implode($glue, $conditions) . ')', $params];
    }

    /**
     * Returns the SQL `WHERE` fragment and parameters for the course context and given file IDs, excluding directory-entry rows.
     *
     * Relies on the tables `{files} f` joined against `{context} c`.
     *
     * Composes onto {@see sql_filter_course_context} with an additional `f.id IN (...)` clause.
     *
     * @param int[] $fileids IDs the files must match.
     * @return array{string, array<string, mixed>} `WHERE` fragment and named parameters.
     * @throws coding_exception
     * @throws dml_exception
     */
    private function sql_filter_selected_files(array $fileids): array {
        global $DB;
        [$where, $params] = $this->sql_filter_course_context();
        [$sqlin, $idparams] = $DB->get_in_or_equal($fileids, SQL_PARAMS_NAMED, 'fileid');
        return ["$where AND f.id $sqlin", $params + $idparams];
    }
}
