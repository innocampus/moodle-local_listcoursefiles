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

use core\lang_string;

/**
 * Supported file types.
 *
 * Maps each case to a list of associated MIME types or MIME type patterns.
 *
 * @package   local_listcoursefiles
 * @copyright 2017 Martin Gauk (@innoCampus, TU Berlin)
 * @author    Jeremy FitzPatrick
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum file_type: string {
    case document = 'document';
    case image = 'image';
    case audio = 'audio';
    case video = 'video';
    case archive = 'archive';
    case hvp = 'hvp';
    case other = 'other';
    case all = 'all';

    /**
     * Returns a list of MIME types and MIME type patterns associated with the file type.
     *
     * @return string[] List of MIME types/patterns; empty for the {@see self::other} type.
     */
    public function get_mime_types(): array {
        return match ($this) {
            self::document => [
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
            self::image => ['image/%'],
            self::audio => ['audio/%'],
            self::video => ['video/%'],
            self::archive => [
                'application/zip',
                'application/x-tar',
                'application/g-zip',
                'application/x-rar-compressed',
                'application/x-7z-compressed',
                'application/vnd.moodle.backup',
            ],
            self::hvp => ['application/zip.h5p'],
            self::other => [],
            self::all => array_merge(
                ...array_map(
                    fn (self $case): array => $case->get_mime_types(),
                    array_filter(self::cases(), fn (self $case): bool => $case !== self::all),
                )
            )
        };
    }

    /**
     * Returns the language string for the file type.
     *
     * @return lang_string Language string for the display name.
     */
    public function get_displayname(): lang_string {
        return new lang_string("filetype:$this->value", 'local_listcoursefiles');
    }

    /**
     * Returns the file type associated with the given MIME type.
     *
     * @param string $mimetype MIME type to check.
     * @return self File type case; {@see self::other} if the MIME type is not supported.
     */
    public static function from_mime_type(string $mimetype): self {
        foreach (self::cases() as $filetype) {
            if ($filetype === self::all) {
                continue;
            }
            foreach ($filetype->get_mime_types() as $pattern) {
                if ($mimetype === $pattern || self::mime_type_matches_pattern($mimetype, $pattern)) {
                    return $filetype;
                }
            }
        }
        return self::other;
    }

    /**
     * Checks if the given MIME type matches the given pattern.
     *
     * @param string $mimetype MIME type to check.
     * @param string $pattern Pattern to check against.
     * @return bool `true`, if the MIME type matches the pattern, `false` otherwise.
     */
    private static function mime_type_matches_pattern(string $mimetype, string $pattern): bool {
        return str_ends_with($pattern, '%') && strncmp($pattern, $mimetype, strlen($pattern) - 1) === 0;
    }
}
