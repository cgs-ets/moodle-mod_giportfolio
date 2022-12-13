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
 * Downloader lib
 *
 * @package    giportfoliotool_donwloader
 * @copyright  2022 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once(__DIR__ . '/lib.php');
require_once($CFG->dirroot . '/mod/giportfolio/locallib.php');
require_once($CFG->libdir . '/filestorage/zip_archive.php');

function giportfoliotool_downloader_get_chapters($giportfolioid) {
    global $DB;
    $chapters = $DB->get_records('giportfolio_chapters', array('giportfolioid' => $giportfolioid), 'id', 'id, title');
    return $chapters;

}
/**
 * Only fetch contributions that have files in them
 */
function giportfoliotool_downloader_get_students($giportfolioid, $chapterid, $id) {
    global $DB, $OUTPUT;

    $sql = "SELECT f.id AS 'fileid', gc.id AS 'contributionid',
                   u.id, u.firstname, u.lastname, u.picture, u.imagealt,
                   gc.title AS 'contributiontitle',
                   gch.title AS 'chaptertitle', gch.id AS 'chapterid',
                   f.contextid, f.component, f.filearea, f.filename
            FROM {user} u
            JOIN {giportfolio_contributions} gc ON gc.userid = u.id
            JOIN {giportfolio_chapters} gch ON gch.id = gc.chapterid
            JOIN {files} f ON f.userid = u.id
            WHERE gch.giportfolioid = ? AND gch.id IN ($chapterid)
            AND f.itemid = gc.id AND f.component = ?
            AND f.filename <> '.'
            ORDER BY  u.id, gch.id";
    $params = ['giportfolioid' => $giportfolioid, 'component' => 'mod_giportfolio'];
    $students = $DB->get_records_sql($sql, $params);
    $options = array(
        'visibletoscreenreaders' => false,
        'includefullname' => true,
    );

    $studentaux = [];
    foreach ($students as $student) {
        $title = new stdClass();
        $title->userid = $student->id;
        $title->chapter = $student->chapterid;
        $title->contributionid = $student->contributionid; // Itemid in the file table.
        $title->contributiontitle = $student->contributiontitle . " ($student->chaptertitle)";
        $file = new stdClass();
        $file->fileid = $student->fileid;
        $file->contextid = $student->contextid;
        $file->component = $student->component;
        $file->filearea = $student->filearea;
        $file->filename = $student->filename;
        $file->chaptertitle = $student->chaptertitle;
        $file->itemid = $student->contributionid;

        if (isset($studentaux[$student->id])) {
            if (!isset($studentaux[$student->id]->contributiontitles['contributions'][$student->contributionid])
                && !in_array($title, $studentaux[$student->id]->contributiontitles['contributions'][$student->contributionid])) {
                $studentaux[$student->id]->contributiontitles['contributions'][$student->contributionid] = $title;
            }
            $studentaux[$student->id]->chaptercontributions[$student->contributionid][] = $file;

        } else {
            $staux = new stdClass();
            $staux->studentid = $student->id;
            $staux->username = $student->firstname . '_' . $student->lastname;
            $staux->id = $id;
            $staux->picture = $OUTPUT->user_picture($student, $options);
            $staux->chaptertitle = $student->chaptertitle;
            $staux->contributionid = $student->contributionid;
            $staux->contributiontitles['contributions'][$student->contributionid] = $title;
            $staux->chaptercontributions[$student->contributionid][] = $file;
            $studentaux[$student->id] = $staux;
        }

    }

    unset($students);
    foreach ($studentaux as $aux) {
        $chaptercontributionsaux = [];
        $aux->chaptertitles = array_values($aux->chaptertitles['chapters']);
        $aux->contributiontitles = array_values($aux->contributiontitles['contributions']);

        foreach ($aux->chaptercontributions as $chac) {
            foreach ($chac as $chc) {
                $chaptercontributionsaux[] = $chc;
            }
        }

        $aux->chaptercontributions = json_encode($chaptercontributionsaux);
        $students['students'][] = $aux;
    }

    return $students;

}


function giportfoliotool_downloader_get_downloader_form_context($id, $cmid) {
    $formcontext = [
        'sessionkey' => sesskey(),
        'id' => $id,
        'cmid' => $cmid,
        'formid' => 'download-contributions-form'
    ];

    return $formcontext;
}

function giportfoliotool_downloader_download_files($items, $giportfolioname) {
    global $DB;
    // Increase the server timeout to handle the creation and sending of large zip files.
    \core_php_time_limit::raise();

    $items = json_decode($items);
    $fs = get_file_storage();
    // Build a list of files to zip.
    $filesforzipping = array();
    // Construct the zip file name.
    $filename = clean_filename($giportfolioname . '.zip'); // Main folder.

    foreach ($items as $item) {
        foreach ($item->items as $i) {
            // print_object($i); exit;
            $files = $fs->get_area_files($i->contextid, $i->component, $i->filearea,  $i->itemid);
            foreach ($files as $file) {
                if ($file->get_filename() == '.') {
                    continue;
                }

                // Get extension from mimetype.
                $extension = '.' . get_extension($file);
                // In case there are files really long names.
                $n = shorten_text(pathinfo($file->get_filename(), PATHINFO_FILENAME), 30, false, '') . $extension;

                $fname  = $item->username. '_' . $n;
                $pathfilename = $i->chaptertitle . '/'. $fname;
               // print_object($pathfilename); exit;
                $filesforzipping[$pathfilename] = $file;
            }
        }
    }

    if (count($filesforzipping) > 0) {
        $zipfile = pack_files($filesforzipping);
        send_temp_file($zipfile, $filename);
    }
}

/**
 * Generate zip file from array of given files - copied from mod_assign 3.10
 *
 * @param array $filesforzipping - array of files to pass into archive_to_pathname.
 *                                 This array is indexed by the final file name and each
 *                                 element in the array is an instance of a stored_file object.
 * @return path of temp file - note this returned file does
 *         not have a .zip extension - it is a temp file.
 *
 * */
function pack_files($filesforzipping) {

        global $CFG;
        // Create path for new zip file.
        $tempzip = tempnam($CFG->tempdir . '/', 'giportfolio_');
        // Zip files.
        $zipper = new zip_packer();

    if ($zipper->archive_to_pathname($filesforzipping, $tempzip)) {
        return $tempzip;
    }
        return false;
}


function get_extension($file) {
    if (pathinfo($file->get_filename(), PATHINFO_EXTENSION) == '') {
        return get_extension_helper($file->get_mimetype());
    } else {
        return  pathinfo($file->get_filename(), PATHINFO_EXTENSION);
    }
}

// Get the file extension.  https://docs.w3cub.com/http/basics_of_http/mime_types/complete_list_of_mime_types.html
// Mac users sometimes dont have the extension in the file. To avoid issues, pick up the mimetype of the file
// and get the extension from it/.
function get_extension_helper($mime) {
        $mimemap = [
            'video/3gpp2'                                                               => '3g2',
            'video/3gp'                                                                 => '3gp',
            'video/3gpp'                                                                => '3gp',
            'application/x-compressed'                                                  => '7zip',
            'audio/x-acc'                                                               => 'aac',
            'audio/ac3'                                                                 => 'ac3',
            'application/postscript'                                                    => 'ai',
            'audio/x-aiff'                                                              => 'aif',
            'audio/aiff'                                                                => 'aif',
            'audio/x-au'                                                                => 'au',
            'video/x-msvideo'                                                           => 'avi',
            'video/msvideo'                                                             => 'avi',
            'video/avi'                                                                 => 'avi',
            'application/x-troff-msvideo'                                               => 'avi',
            'application/macbinary'                                                     => 'bin',
            'application/mac-binary'                                                    => 'bin',
            'application/x-binary'                                                      => 'bin',
            'application/x-macbinary'                                                   => 'bin',
            'image/bmp'                                                                 => 'bmp',
            'image/x-bmp'                                                               => 'bmp',
            'image/x-bitmap'                                                            => 'bmp',
            'image/x-xbitmap'                                                           => 'bmp',
            'image/x-win-bitmap'                                                        => 'bmp',
            'image/x-windows-bmp'                                                       => 'bmp',
            'image/ms-bmp'                                                              => 'bmp',
            'image/x-ms-bmp'                                                            => 'bmp',
            'application/bmp'                                                           => 'bmp',
            'application/x-bmp'                                                         => 'bmp',
            'application/x-win-bitmap'                                                  => 'bmp',
            'application/cdr'                                                           => 'cdr',
            'application/coreldraw'                                                     => 'cdr',
            'application/x-cdr'                                                         => 'cdr',
            'application/x-coreldraw'                                                   => 'cdr',
            'image/cdr'                                                                 => 'cdr',
            'image/x-cdr'                                                               => 'cdr',
            'zz-application/zz-winassoc-cdr'                                            => 'cdr',
            'application/mac-compactpro'                                                => 'cpt',
            'application/pkix-crl'                                                      => 'crl',
            'application/pkcs-crl'                                                      => 'crl',
            'application/x-x509-ca-cert'                                                => 'crt',
            'application/pkix-cert'                                                     => 'crt',
            'text/css'                                                                  => 'css',
            'text/x-comma-separated-values'                                             => 'csv',
            'text/comma-separated-values'                                               => 'csv',
            'application/vnd.msexcel'                                                   => 'csv',
            'application/x-director'                                                    => 'dcr',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => 'docx',
            'application/x-dvi'                                                         => 'dvi',
            'message/rfc822'                                                            => 'eml',
            'application/x-msdownload'                                                  => 'exe',
            'video/x-f4v'                                                               => 'f4v',
            'audio/x-flac'                                                              => 'flac',
            'video/x-flv'                                                               => 'flv',
            'image/gif'                                                                 => 'gif',
            'application/gpg-keys'                                                      => 'gpg',
            'application/x-gtar'                                                        => 'gtar',
            'application/x-gzip'                                                        => 'gzip',
            'application/mac-binhex40'                                                  => 'hqx',
            'application/mac-binhex'                                                    => 'hqx',
            'application/x-binhex40'                                                    => 'hqx',
            'application/x-mac-binhex40'                                                => 'hqx',
            'text/html'                                                                 => 'html',
            'image/x-icon'                                                              => 'ico',
            'image/x-ico'                                                               => 'ico',
            'image/vnd.microsoft.icon'                                                  => 'ico',
            'text/calendar'                                                             => 'ics',
            'application/java-archive'                                                  => 'jar',
            'application/x-java-application'                                            => 'jar',
            'application/x-jar'                                                         => 'jar',
            'image/jp2'                                                                 => 'jp2',
            'video/mj2'                                                                 => 'jp2',
            'image/jpx'                                                                 => 'jp2',
            'image/jpm'                                                                 => 'jp2',
            'image/jpeg'                                                                => 'jpeg',
            'image/pjpeg'                                                               => 'jpeg',
            'application/x-javascript'                                                  => 'js',
            'application/json'                                                          => 'json',
            'text/json'                                                                 => 'json',
            'application/vnd.google-earth.kml+xml'                                      => 'kml',
            'application/vnd.google-earth.kmz'                                          => 'kmz',
            'text/x-log'                                                                => 'log',
            'audio/x-m4a'                                                               => 'm4a',
            'application/vnd.mpegurl'                                                   => 'm4u',
            'audio/midi'                                                                => 'mid',
            'application/vnd.mif'                                                       => 'mif',
            'video/quicktime'                                                           => 'mov',
            'video/x-sgi-movie'                                                         => 'movie',
            'audio/mpeg'                                                                => 'mp3',
            'audio/mpg'                                                                 => 'mp3',
            'audio/mpeg3'                                                               => 'mp3',
            'audio/mp3'                                                                 => 'mp3',
            'video/mp4'                                                                 => 'mp4',
            'video/mpeg'                                                                => 'mpeg',
            'application/oda'                                                           => 'oda',
            'audio/ogg'                                                                 => 'ogg',
            'video/ogg'                                                                 => 'ogg',
            'application/ogg'                                                           => 'ogg',
            'application/x-pkcs10'                                                      => 'p10',
            'application/pkcs10'                                                        => 'p10',
            'application/x-pkcs12'                                                      => 'p12',
            'application/x-pkcs7-signature'                                             => 'p7a',
            'application/pkcs7-mime'                                                    => 'p7c',
            'application/x-pkcs7-mime'                                                  => 'p7c',
            'application/x-pkcs7-certreqresp'                                           => 'p7r',
            'application/pkcs7-signature'                                               => 'p7s',
            'application/pdf'                                                           => 'pdf',
            'application/octet-stream'                                                  => 'pdf',
            'application/x-x509-user-cert'                                              => 'pem',
            'application/x-pem-file'                                                    => 'pem',
            'application/pgp'                                                           => 'pgp',
            'application/x-httpd-php'                                                   => 'php',
            'application/php'                                                           => 'php',
            'application/x-php'                                                         => 'php',
            'text/php'                                                                  => 'php',
            'text/x-php'                                                                => 'php',
            'application/x-httpd-php-source'                                            => 'php',
            'image/png'                                                                 => 'png',
            'image/x-png'                                                               => 'png',
            'application/powerpoint'                                                    => 'ppt',
            'application/vnd.ms-powerpoint'                                             => 'ppt',
            'application/vnd.ms-office'                                                 => 'ppt',
            'application/msword'                                                        => 'doc',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/x-photoshop'                                                   => 'psd',
            'image/vnd.adobe.photoshop'                                                 => 'psd',
            'audio/x-realaudio'                                                         => 'ra',
            'audio/x-pn-realaudio'                                                      => 'ram',
            'application/x-rar'                                                         => 'rar',
            'application/rar'                                                           => 'rar',
            'application/x-rar-compressed'                                              => 'rar',
            'audio/x-pn-realaudio-plugin'                                               => 'rpm',
            'application/x-pkcs7'                                                       => 'rsa',
            'text/rtf'                                                                  => 'rtf',
            'text/richtext'                                                             => 'rtx',
            'video/vnd.rn-realvideo'                                                    => 'rv',
            'application/x-stuffit'                                                     => 'sit',
            'application/smil'                                                          => 'smil',
            'text/srt'                                                                  => 'srt',
            'image/svg+xml'                                                             => 'svg',
            'application/x-shockwave-flash'                                             => 'swf',
            'application/x-tar'                                                         => 'tar',
            'application/x-gzip-compressed'                                             => 'tgz',
            'image/tiff'                                                                => 'tiff',
            'text/plain'                                                                => 'txt',
            'text/x-vcard'                                                              => 'vcf',
            'application/videolan'                                                      => 'vlc',
            'text/vtt'                                                                  => 'vtt',
            'audio/x-wav'                                                               => 'wav',
            'audio/wave'                                                                => 'wav',
            'audio/wav'                                                                 => 'wav',
            'application/wbxml'                                                         => 'wbxml',
            'video/webm'                                                                => 'webm',
            'audio/x-ms-wma'                                                            => 'wma',
            'application/wmlc'                                                          => 'wmlc',
            'video/x-ms-wmv'                                                            => 'wmv',
            'video/x-ms-asf'                                                            => 'wmv',
            'application/xhtml+xml'                                                     => 'xhtml',
            'application/excel'                                                         => 'xl',
            'application/msexcel'                                                       => 'xls',
            'application/x-msexcel'                                                     => 'xls',
            'application/x-ms-excel'                                                    => 'xls',
            'application/x-excel'                                                       => 'xls',
            'application/x-dos_ms_excel'                                                => 'xls',
            'application/xls'                                                           => 'xls',
            'application/x-xls'                                                         => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'         => 'xlsx',
            'application/vnd.ms-excel'                                                  => 'xlsx',
            'application/xml'                                                           => 'xml',
            'text/xml'                                                                  => 'xml',
            'text/xsl'                                                                  => 'xsl',
            'application/xspf+xml'                                                      => 'xspf',
            'application/x-compress'                                                    => 'z',
            'application/x-zip'                                                         => 'zip',
            'application/zip'                                                           => 'zip',
            'application/x-zip-compressed'                                              => 'zip',
            'application/s-compressed'                                                  => 'zip',
            'multipart/x-zip'                                                           => 'zip',
            'text/x-scriptzsh'                                                          => 'zsh',
        ];

        return isset($mimemap[$mime]) === true ? $mimemap[$mime] : false;
}





