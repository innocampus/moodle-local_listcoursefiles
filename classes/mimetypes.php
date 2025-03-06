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

use coding_exception;

/**
 * Class mimetypes
 *
 * @package   local_listcoursefiles
 * @copyright 2017 Martin Gauk (@innoCampus, TU Berlin)
 * @author    Jeremy FitzPatrick
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mimetypes {
    /**
     * @var string[][] Mapping of file types to possible mime types.
     */
    static protected array $mimetypes = [
        'document' => [
            'application/epub+zip',
            'application/msword',
            'application/pdf',
            'application/postscript',
            'application/vnd.ms-%',
            'application/vnd.oasis.opendocument%',
            'application/vnd.openxmlformats-officedocument%',
            'application/vnd.sun.xml%',
            'application/x-digidoc',
            'application/x-javascript',
            'application/x-latex',
            'application/x-ms%',
            'application/x-tex%',
            'application/xhtml+xml',
            'application/xml',
            'document%',
            'spreadsheet',
            'text/%',
        ],
        'image' => ['image/%'],
        'audio' => ['audio/%'],
        'video' => ['video/%'],
        'archive' => [
            'application/zip',
            'application/x-tar',
            'application/g-zip',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
            'application/vnd.moodle.backup',
        ],
        'hvp' => ['application/zip.h5p'],
    ];

    /**
     * Try to get the name of the file type in the user's lang
     *
     * @param string $mimetype
     * @return string
     * @throws coding_exception
     */
    public static function get_file_type_translation(string $mimetype): string {
        foreach (self::$mimetypes as $name => $types) {
            foreach ($types as $mime) {
                if ($mime === $mimetype ||
                    (substr($mime, -1) === '%' && strncmp($mime, $mimetype, strlen($mime) - 1) === 0)) {
                    return get_string('filetype_' . $name, 'local_listcoursefiles');
                }
            }
        }
        return $mimetype;
    }

    /**
     * Getter for mime types
     *
     * @return string[][]
     */
    public static function get_mime_types(): array {
        return self::$mimetypes;
    }
}
