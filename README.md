moodle-local_listcoursefiles
============================

This extension allows teachers to view a list of all files in a course and to change the license for each file. It is also
possible to download the files in a ZIP archive.

Adding a new license
====================

Adding a license is not straightforward in Moodle.

1. You need to add the license to the database table "license" manually. For example:
INSERT INTO license (shortname, fullname, source, enabled, version) VALUES ('short', 'fullname', '', 0, 2016112100);
2. Add the english fullname to lang/en/license.php, e.g. append to the file:
$string['short'] = 'fullname';
3. Specify other translations via Site administration -> Language -> Language customisation
4. Enable the new license on Site administration -> Plugins -> Licenses -> Manage licenses
5. If you want to change the ordering of the licenses, you need to modify the row with the name "licenses" in the database table "config".
