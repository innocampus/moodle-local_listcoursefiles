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
use Generator;

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
    case DOCUMENT = 'document';
    case IMAGE = 'image';
    case AUDIO = 'audio';
    case VIDEO = 'video';
    case ARCHIVE = 'archive';
    case HVP = 'hvp';
    case OTHER = 'other';

    /**
     * Returns a list of MIME types and MIME type patterns associated with the file type.
     *
     * @return string[] List of MIME types/patterns; empty for the {@see self::OTHER} type.
     */
    public function get_mime_types(): array {
        return match ($this) {
            self::DOCUMENT => [
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
            self::IMAGE => ['image/%'],
            self::AUDIO => ['audio/%'],
            self::VIDEO => ['video/%'],
            self::ARCHIVE => [
                'application/zip',
                'application/x-tar',
                'application/g-zip',
                'application/x-rar-compressed',
                'application/x-7z-compressed',
                'application/vnd.moodle.backup',
            ],
            self::HVP => ['application/zip.h5p'],
            self::OTHER => [],
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
     * Produces all supported MIME types and MIME type patterns.
     *
     * @return Generator<string> All supported MIME types (indexed contiguously).
     */
    public static function iter_all_mime_types(): Generator {
        foreach (file_type::cases() as $filetype) {
            // Cannot use `yield from` here because we want contiguous keys.
            foreach ($filetype->get_mime_types() as $pattern) {
                yield $pattern;
            }
        }
    }

    /**
     * Returns the file type associated with the given MIME type.
     *
     * @param string $mimetype MIME type to check.
     * @return self File type case; {@see self::OTHER} if the MIME type is not supported.
     */
    public static function from_mime_type(string $mimetype): self {
        foreach (self::cases() as $filetype) {
            foreach ($filetype->get_mime_types() as $pattern) {
                if (self::mime_type_matches_pattern($mimetype, $pattern)) {
                    return $filetype;
                }
            }
        }
        return self::OTHER;
    }

    /**
     * Checks if the given MIME type matches the given pattern.
     *
     * 1) If the MIME type is equal to the pattern, it is considered a match.
     * 2) If the pattern ends with `%`, a corresponding prefix match is attempted.
     *
     * @param string $mimetype MIME type to check.
     * @param string $pattern Pattern to check against.
     * @return bool `true`, if the MIME type matches the pattern, `false` otherwise.
     */
    private static function mime_type_matches_pattern(string $mimetype, string $pattern): bool {
        if ($mimetype === $pattern) {
            return true;
        }
        return str_ends_with($pattern, '%') && strncmp($pattern, $mimetype, strlen($pattern) - 1) === 0;
    }
}
