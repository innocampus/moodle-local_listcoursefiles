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

namespace local_listcoursefiles\components;

use local_listcoursefiles\course_file;

/**
 * Class mod_feedback
 * @package local_listcoursefiles
 * @author Jeremy FitzPatrick
 * @copyright 2022 Te Wānanga o Aotearoa
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_feedback extends course_file {
    /**
     * Try to get the download url for a file.
     *
     * @param object $file
     * @return null|\moodle_url
     */
    public function get_file_download_url($file) {
        if ($file->filearea === 'item' || $file->filearea === 'page_after_submit') {
            return new \moodle_url('/pluginfile.php/' . $file->contextid . '/' . $file->component . '/' .
                $file->filearea . '/' . $file->itemid . $file->filepath . $file->filename);
        } else {
            return parent::get_file_download_url($file);
        }
    }

    /**
     * Creates the URL for the editor where the file is added
     *
     * @param object $file
     * @return \moodle_url|null
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_edit_url($file) {
        global $DB;
        $url = null;
        if ($file->filearea === 'item' || $file->filearea === 'page_after_submit') {
            $sql = 'SELECT cm.* FROM {context} ctx
                        JOIN {course_modules} cm ON cm.id = ctx.instanceid
                        WHERE ctx.id = ?';
            $mod = $DB->get_record_sql($sql, [$file->contextid]);
            $url = new \moodle_url('/course/modedit.php?', ['update' => $mod->id]);
        } else {
            $url = parent::get_edit_url($file);
        }

        return $url;
    }

    /**
     * Checks if embedded files have been used
     *
     * @param object $file
     * @return bool
     */
    public function is_file_used($file) {
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
        } else {
            return parent::is_file_used($file);
        }
    }
}