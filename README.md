moodle-local_listcoursefiles
============================

This extension allows teachers to view a list of all files in a course and to change the license for each file. It is also
possible to download the files in a ZIP archive.

Adding a new license
====================

Since Moodle version `3.9` you can add a custom license via Moodle's [license manager](https://docs.moodle.org/403/en/Licences#Licence_manager) in the Site administration.

Expired files
=============

We are using this plugin at Technische Universität Berlin in order to conform to german law (§ 52a UrhG).
Copyrighted texts have to be made unavailable after a course ends. Since only a few of our teachers upload
copyrighted texts and we want our students to be able to view their old courses (and non-copyrighted files),
we use this plugin as a solution.

Our teachers specify the license of each file (through this plugin or the Moodle file picker). That way, we
can identify the copyrighted files and make only these unavailable. We replaced the default licenses from
Moodle by "License unexamined", "Copyrighted (provided in accordance with §52a UrhG)", "Approved by author",
"Open content", and "Public domain". "License unexamined" is the default license.

The files (documents) that are currently unavailable for students can be highlighted by the plugin.
The following settings need to be defined in the config.php:
* array $CFG->fileexpirylicenses which licenses (shortnames) expire
* int $CFG->fileexpirydate when do files expire (unix time)
* array $CFG->filemimetypes['document'] mime types of documents

Some modifications to the Moodle core are required to restrict the download of copyrighted files.
Our patch for Moodle 3.2 can be found here:
https://github.com/innocampus/patches/blob/master/Moodle32-Check-file-license-and-expiry-date.patch
