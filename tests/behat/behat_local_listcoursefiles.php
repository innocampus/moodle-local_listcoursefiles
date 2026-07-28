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
 * Definition of the {@see behat_local_listcoursefiles} class.
 *
 * @package   local_listcoursefiles
 * @copyright 2026 Daniel Fainberg, TU Berlin
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * {@noinspection PhpIllegalPsrClassPathInspection}
 */

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Then;

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Behat step definitions for local_listcoursefiles.
 *
 * @package   local_listcoursefiles
 * @copyright 2026 Daniel Fainberg, TU Berlin
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_listcoursefiles extends behat_base {
    /**
     * Submits the file list form's download action and asserts the resulting zip archive contains the named files.
     *
     * The overview page downloads selected files by POSTing the file list form (`action=download`).
     * Mirrors core's {@see behat_general::download_file_from_link} for plain download links — this step reconstructs the request
     * the browser would send and fetches it over the same session via {@see download_file_content}:
     * - Reads the POST target, the `sesskey`, and every ticked file checkbox from the live DOM, so it submits exactly what the
     *   rendered form would.
     * - Replays the request carrying the browser's `MoodleSession` cookie, then opens the returned zip and checks that each file
     *   named in the table is present. (Does not check size or contents.)
     *
     * @param TableNode $table Single-column table of file names expected inside the downloaded archive.
     * @throws ExpectationException The form is missing, nothing is selected, the response is not a zip, or a file is absent.
     *
     * {@noinspection PhpUnused}
     */
    #[Then('the selected course files should download as a zip containing:')]
    public function selected_course_files_should_download_as_a_zip_containing(TableNode $table): void {
        $form = $this->getSession()->getPage()->find('css', 'form#filelist');
        if ($form === null) {
            throw new ExpectationException('The file list form was not found on the page.', $this->getSession());
        }
        $sesskey = $form->find('css', 'input[name=sesskey]');
        if ($sesskey === null) {
            throw new ExpectationException('No sesskey field was found in the file list form.', $this->getSession());
        }
        // Collect the IDs of the ticked file checkboxes; their name is `file[<id>]`, and the server keys off that ID.
        $files = [];
        foreach ($form->findAll('css', 'input.local_listcoursefiles_filecheckbox') as $checkbox) {
            if ($checkbox->isChecked() && preg_match('/^file\[(\d+)]$/', (string) $checkbox->getAttribute('name'), $m)) {
                $files[$m[1]] = 'on';
            }
        }
        if (empty($files)) {
            throw new ExpectationException('No file checkboxes are selected to download.', $this->getSession());
        }
        // Replay the form POST over the browser's session.
        $postdata = http_build_query(
            data: ['sesskey' => $sesskey->getValue(), 'action' => 'download', 'file' => $files],
            arg_separator: '&', // Necessary for Moodle <5.2 because there it is 'amp;' by default.
        );
        $cookie = $this->getSession()->getCookie('MoodleSession');
        $content = download_file_content($form->getAttribute('action'), ['Cookie' => "MoodleSession=$cookie"], $postdata);
        $zippath = make_request_directory() . '/downloaded.zip';
        file_put_contents($zippath, $content);
        $zip = new ZipArchive();
        if ($zip->open($zippath) !== true) {
            throw new ExpectationException('The downloaded file could not be opened as a zip archive.', $this->getSession());
        }
        foreach ($table->getRows() as $row) {
            $expected = trim($row[0]);
            if ($zip->locateName($expected) === false) {
                $zip->close();
                throw new ExpectationException("The downloaded zip archive does not contain '$expected'.", $this->getSession());
            }
        }
        $zip->close();
    }
}
