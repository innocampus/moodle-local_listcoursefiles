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

use core\exception\coding_exception;
use core\exception\moodle_exception;
use core\lang_string;
use dml_exception;
use local_listcoursefiles\components\mod;
use moodle_url;
use stdClass;

/**
 * Represents a file uploaded somewhere in a course.
 *
 * For specific contexts, this class may be extended to provide additional information about the file.
 * The following methods may be overridden:
 * - {@see get_component_url}
 * - {@see get_displayname}
 * - {@see get_download_url}
 * - {@see get_edit_url}
 * - {@see get_embedding_context}
 * - {@see is_used}
 *
 * @property-read string $displayname Name of the file for display purposes.
 * @property-read string|false $downloadurl URL to download the file.
 * @property-read string|false $componenturl URL to the component the file was uploaded to.
 * @property-read string|false $editurl URL to edit the component the file was uploaded to.
 * @property-read string $isuseddisplay Text for whether an embedded file is used somewhere in the course.
 * @property-read string $filesizedisplay Human-readable size of the file.
 * @property-read string $typedisplay Human-readable type of the file.
 * @property-read string $licensedisplay Name of the license.
 * @property-read string $componentdisplay Name of the component.
 * @property-read string $usernamedisplay Displayname of the person who uploaded the file.
 *
 * @package   local_listcoursefiles
 * @copyright 2017 Martin Gauk (@innoCampus, TU Berlin)
 * @author    Jeremy FitzPatrick
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_file {
    /** @var string[] TODO Remove together with the {@see __get} method. */
    private const DYNAMIC_PROPERTIES = [
        'displayname',
        'downloadurl',
        'componenturl',
        'editurl',
        'isuseddisplay',
        'filesizedisplay',
        'typedisplay',
        'licensedisplay',
        'componentdisplay',
        'usernamedisplay',
    ];

    /** @var stdClass Information about the user who uploaded the file. */
    private readonly stdClass $user;

    /**
     * Constructs a new instance of this class or an appropriate subclass from an untyped database record.
     *
     * The record must have a property for every column in the `files` table, as well as a `contextlevel` and `instanceid`.
     * All other properties are interpreted as fields of the joined `user` table.
     *
     * @param stdClass $record
     * @param int $courseid
     * @return course_file
     */
    final public static function from_record(stdClass $record, int $courseid): course_file {
        $classname = "\\local_listcoursefiles\\components\\$record->component";
        if (class_exists($classname)) {
            $class = $classname;
        } else if (str_starts_with($record->component, 'mod_')) {
            $class = mod::class;
        } else {
            $class = self::class;
        }
        return new $class(...(array) $record, courseid: $courseid);
    }

    /**
     * Private constructor propagating almost all arguments to public readonly properties.
     *
     * Marked `private` so that instances (including those of subclasses) can only be built through the {@see from_record}
     * factory. Subclass instantiation still works: the `new $class(...)` call inside {@see from_record} lives in
     * `course_file` scope and therefore sees this private constructor on the subclass.
     *
     * Most of the arguments/properties match the columns of the `files` table. In addition, this expects the following:
     * - {@see self::$contextlevel} from the joined `context` table.
     * - {@see self::$instanceid} from the joined `context` table.
     * - {@see self::$courseid} representing the course the file was uploaded in.
     *
     * Any additional named arguments are interpreted as fields of the joined `user` table, representing the uploader of the file.
     *
     * @param int $id ID of the file.
     * @param string $contenthash Hash of the file content.
     * @param string $pathnamehash Hash of the file path.
     * @param int $contextid ID of the context the file is associated with.
     * @param string $component The name of the component the file is associated with.
     * @param string $filearea The name of the file area the file is associated with.
     * @param int $itemid ID of the item the file is associated with.
     * @param string $filepath Relative path to the file from the module content root.
     * @param string $filename Full name of the file.
     * @param int|null $userid ID of the user who uploaded the file.
     * @param int $filesize Size of the file in bytes.
     * @param string|null $mimetype MIME type of the file.
     * @param int $status Status of the file; greater than 0 means something is wrong.
     * @param string|null $source Source of the file, if imported from an external source.
     * @param string|null $author Original author of the file.
     * @param string|null $license License of the file.
     * @param int $timecreated Timestamp when the file was uploaded.
     * @param int $timemodified Timestamp when the file was last modified.
     * @param int $sortorder Sorting order relative to other files.
     * @param int|null $referencefileid ID of the repository file that this is a reference to, if any.
     * @param int $contextlevel Level of the associated context (from the joined `context` table).
     * @param int $instanceid ID of the associated instance (from the joined `context` table).
     * @param int $courseid ID of the course the file was uploaded in.
     * @param mixed ...$user Fields of the joined `user` table.
     */
    final private function __construct(
        /** @var int ID of the file. */
        public readonly int $id,
        /** @var string Hash of the file content. */
        public readonly string $contenthash,
        /** @var string Hash of the file path. */
        public readonly string $pathnamehash,
        /** @var int ID of the context the file is associated with. */
        public readonly int $contextid,
        /** @var string The name of the component the file is associated with. */
        public readonly string $component,
        /** @var string The name of the file area the file is associated with. */
        public readonly string $filearea,
        /** @var int ID of the item the file is associated with. */
        public readonly int $itemid,
        /** @var string Relative path to the file from the module content root. */
        public readonly string $filepath,
        /** @var string Full name of the file. */
        public readonly string $filename,
        /** @var int|null ID of the user who uploaded the file. */
        public readonly int|null $userid,
        /** @var int Size of the file in bytes. */
        public readonly int $filesize,
        /** @var string|null MIME type of the file. */
        public readonly string|null $mimetype,
        /** @var int Status of the file; greater than 0 means something is wrong. */
        public readonly int $status,
        /** @var string|null Source of the file, if imported from an external source. */
        public readonly string|null $source,
        /** @var string|null Original author of the file. */
        public readonly string|null $author,
        /** @var string|null License of the file. */
        public readonly string|null $license,
        /** @var int Timestamp when the file was uploaded. */
        public readonly int $timecreated,
        /** @var int Timestamp when the file was last modified. */
        public readonly int $timemodified,
        /** @var int Sorting order relative to other files. */
        public readonly int $sortorder,
        /** @var int|null ID of the repository file that this is a reference to, if any. */
        public readonly int|null $referencefileid,
        /** @var int Level of the associated context (from the joined `context` table). */
        public readonly int $contextlevel,
        /** @var int ID of the associated instance (from the joined `context` table). */
        public readonly int $instanceid,
        /** @var int ID of the course the file was uploaded in. */
        public readonly int $courseid,
        mixed ...$user,
    ) {
        $this->user = (object) $user;
    }

    /**
     * Required for the template engine to recognize the dynamic properties facilitated by the magic {@see __get} method.
     *
     * TODO Remove together with the {@see __get} method.
     *
     * @param string $name Name of the property to check.
     * @return bool `true`, if the property exists, `false` otherwise.
     */
    public function __isset(string $name): bool {
        return in_array($name, self::DYNAMIC_PROPERTIES);
    }

    /**
     * Magic getter for the dynamic read-only properties.
     *
     * Exists mainly for rendering the overview template.
     *
     * TODO Replace this method with nice property `get`-hooks, once PHP 8.4+ becomes the minimum requirement.
     *
     * @param string $name Name of the property to get.
     * @return string|false Value of the property.
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function __get(string $name): string|false {
        return match ($name) {
            'componentdisplay' => component::get_display_name($this->component),
            'componenturl'     => $this->get_component_url()?->out() ?? false,
            'displayname'      => $this->get_displayname(),
            'downloadurl'      => $this->get_download_url()?->out() ?? false,
            'editurl'          => $this->get_edit_url()?->out(escaped: false) ?? false,
            'filesizedisplay'  => display_size($this->filesize),
            'isuseddisplay'    => self::get_is_used_text($this->is_used()),
            'licensedisplay'   => licenses::get_license_name_color($this->license ?? ''),
            'typedisplay'      => $this->get_type_displayname(),
            'usernamedisplay'  => fullname($this->user),
            default            => throw new coding_exception("No such property: $name")
        };
    }

    /**
     * Utility method for getting the text for whether a file is used or not.
     *
     * @param bool|null $used `true`, if the file is used; `false`, if it is not; `null` if it is not known.
     * @return lang_string Text for whether the file is used or not.
     */
    private static function get_is_used_text(bool|null $used): lang_string {
        return match ($used) {
            true    => new lang_string('yes'),
            false   => new lang_string('no'),
            default => new lang_string('not_tested', 'local_listcoursefiles'),
        };
    }

    /**
     * Returns a name for the file for display purposes.
     *
     * @return string File name.
     */
    protected function get_displayname(): string {
        return $this->filename;
    }

    /**
     * Returns the direct download URL for the file.
     *
     * **Subclasses for specific components may override this method.**
     *
     * @return moodle_url|null Download URL for the file; `null` if not found.
     * @throws moodle_exception
     */
    protected function get_download_url(): moodle_url|null {
        if ($this->filearea == 'intro') {
            return $this->get_standard_download_url();
        }
        return null;
    }

    /**
     * Returns the standard download URL for a file.
     *
     * Most `pluginfile.php` URLs are constructed the same way.
     *
     * @param bool $insertitemid Whether to insert the `itemid` of the file into the URL.
     * @return moodle_url Standard download URL for the file.
     * @throws moodle_exception
     */
    final protected function get_standard_download_url(bool $insertitemid = true): moodle_url {
        $url = "/pluginfile.php/$this->contextid/$this->component/$this->filearea";
        if ($insertitemid) {
            $url .= "/$this->itemid";
        }
        $url .= $this->filepath . $this->filename;
        return new moodle_url($url);
    }

    /**
     * Returns the URL to view the associated component.
     *
     * **Subclasses for specific components may override this method.**
     *
     * @return moodle_url|null URL to the component; `null` if not applicable or not found.
     * @throws moodle_exception
     */
    protected function get_component_url(): moodle_url|null {
        if ($this->contextlevel === CONTEXT_MODULE) {
            $modinfo = get_fast_modinfo($this->courseid);
            if (isset($modinfo->cms[$this->instanceid])) {
                return $modinfo->cms[$this->instanceid]->url;
            }
        }
        return null;
    }

    /**
     * Checks if the file is currently used or embedded somewhere.
     *
     * **Subclasses for specific components may override this method.**
     *
     * @return bool|null `true`, if the file is used/embedded; `false`, if it is not; `null` if it is not known.
     */
    protected function is_used(): bool|null {
        $text = $this->get_embedding_context();
        if ($text === false) {
            return null;
        }
        return $this->is_embedded_in($text);
    }

    /**
     * Returns the text that may contain the embedded file in the associated component.
     *
     * **Subclasses for specific components may override this method.**
     *
     * @return string|false Text that may contain the embedded file; `false` if not embedded or the associated record was not found.
     */
    protected function get_embedding_context(): string|false {
        return false;
    }

    /**
     * Checks whether the given text contains the embedded file.
     *
     * @param string $text Text to check.
     * @return bool `true`, if the text contains the embedded file; `false`, if it does not.
     */
    final protected function is_embedded_in(string $text): bool {
        return str_contains($text, '@@PLUGINFILE@@/' . rawurlencode($this->filename));
    }

    /**
     * Returns the URL for editing the associated component/area.
     *
     * **Subclasses for specific components may override this method.**
     *
     * @return moodle_url|null URL for editing; `null` if not applicable or not found.
     */
    protected function get_edit_url(): moodle_url|null {
        return null;
    }

    /**
     * Returns the language string for the {@see filetype} associated with the file's MIME type.
     *
     * @see filetype::from_mime_type
     * @see filetype::get_displayname
     *
     * @return lang_string|string Language string for the associated file type; just the MIME type string, if it is not supported.
     */
    private function get_type_displayname(): lang_string|string {
        $filetype = filetype::from_mime_type($this->mimetype);
        return $filetype === filetype::other ? $this->mimetype : $filetype->get_displayname();
    }
}
