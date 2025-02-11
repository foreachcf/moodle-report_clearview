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
 * Advanced Report class for clearview report.
 *
 * @package     report_clearview
 * @copyright   2024 Andrew Caya <andrewscaya@yahoo.ca>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace report_clearview\reports;

defined('MOODLE_INTERNAL') || die;

/**
 * Advanced Report class for clearview report.
 *
 * This class is the Clearview plugin's abstract advanced_report class.
 */
abstract class advanced_report  {

    /** Sets the context level for the report. Default is CONTEXT_SYSTEM */
    const CONTEXTLEVEL = CONTEXT_SYSTEM;

    /** Sets the default Web root for all reports. Default is ALL WEB ROOTS '*' (in case of multi-tenancy). */
    const WWWROOT = '*';

    /** @var string $id Contains the advanced report's id. */
    public static string $id = '0';

    /** @var string $courseid Contains the course's id. Default is SITEID (for all courses). */
    public static string $courseid = SITEID;

    /** @var string $title Contains the report's title. Default is an empty string. */
    public string $title = '';

    /** @var array $reportcolumns Two-dimensional array of language-based column names. */
    protected array $reportcolumns = ['en' => []];

    /** @var array $reportdata Two-dimensional array of data. */
    protected mixed $reportdata = [];

    /**
     * Construct this advanced report.
     *
     * @param array $reportcolumns Array of columns names.
     */
    public function __construct(string $id, array $reportcolumns, string $courseid = SITEID, string $title = '') {
        $this->id = $id;

        $this->courseid = $courseid;

        $this->title = $title;

        $this->reportcolumns = $reportcolumns;

        if (static::CONTEXTLEVEL == CONTEXT_SYSTEM) {
            $coursecatcache = \cache::make('report_clearview', 'categorydatacache');
            $basecachekey = 'report_clearview_catcache_advreport_' . $this->id;
            $advreportdatapayload = $coursecatcache->get($basecachekey);

            if (!empty($advreportdatapayload)) {
                $this->reportdata = unserialize($advreportdatapayload);
            } else {
                $this->build_report();
            }
        } else {
            $this->build_report();
        }
    }

    /**
     * Build the two-dimensional array contained in $this->reportdata.
     *
     * @return array|bool
     */
    protected function build_report() {
        return $this->reportdata;
    }

    /**
     * Export this data, so it can be used within a Mustache template.
     *
     * @return array HTML Table of data
     */
    public function export_for_template() {
        $dataelements = [];

        $t = new \html_table();
        $t->id = 'data-table-1';
        $t->attributes['class'] = 'table table-striped table-sm';
        $t->class = $t->attributes['class'];

        $t->head = $this->reportcolumns[current_language()];

        if (!empty($this->reportdata)) {
            foreach ($this->reportdata as $data) {
                $row = new \html_table_row();

                foreach ($data as $datum) {
                    $cell = new \html_table_cell();
                    $cell->text = $datum;
                    $row->cells[] = $cell;
                }

                $t->data[] = $row;
            }
        }

        $dataelements['report_body'] = \html_writer::table($t);

        $output = '';

        if ($t->responsive) {
            $output .= '<div class="table-responsive">';
        }

        $output .= '<table id="' . $t->id . '" class="' . $t->attributes['class'] . '">';
        $output .= '<thead>';
        $output .= '<tr>';

        foreach ($t->head as $header) {
            $output .= '<th>' . $header . '</th>';
        }

        $output .= '</tr>';
        $output .= '</thead>';

        $output .= '<tbody>';

        if (!empty($t->data)) {
            foreach ($t->data as $row) {
                if (isset($row->id)) {
                    $output .= '<tr id="'. $row->id . '">';
                } else {
                    $output .= '<tr>';
                }

                foreach ($row->cells as $cell) {
                    if (isset($cell->id)) {
                        $output .= '<td id="'. $cell->id . '">';
                    } else {
                        $output .= '<td>';
                    }

                    $output .= $cell->text;

                    $output .= '</td>';
                }

                $output .= '</tr>';
            }
        }

        $output .= '</tbody>';

        $output .= '</table>';

        if ($t->responsive) {
            $output .= '</div>';
        }

        $dataelements['report_body'] = $output;

        return $dataelements;
    }

    /**
     * Exports report data, in CSV format (UTF-8, semicolon, CRLF).
     *
     * @return string CSV string
     */
    public function export_to_csv() {
        global $CFG, $USER;

        // This implementation follows RFC 4180, in case of corrupt or unusual data in the database (Web incompatible characters).
        $matrix = [];

        foreach ($this->reportcolumns[current_language()] as $key => $columnhead) {
            $matrix[0][$key] = $this->filter_control_chars(html_entity_decode(htmlspecialchars_decode(strip_tags(nl2br($columnhead)))));
        }

        if (!empty($this->reportdata)) {
            $i = 0;

            foreach ($this->reportdata as $rdata) {
                $j = 0;

                foreach ($rdata as $item) {
                    $matrix[$i + 1][$j] = $this->filter_control_chars(html_entity_decode(htmlspecialchars_decode(strip_tags(nl2br($item)))));
                    $j++;
                }

                $i++;
            }
        }

        $filename = 'clearview_csvreport_' . $USER->id . '_' . substr(bin2hex(random_bytes(32)), 0, 32);
        make_temp_directory('csvimport' . DIRECTORY_SEPARATOR . $USER->id);
        $path = $CFG->tempdir . DIRECTORY_SEPARATOR . 'csvimport' . DIRECTORY_SEPARATOR . $USER->id . DIRECTORY_SEPARATOR . $filename;

        // Check to see if the file exists. If so, delete it.
        if (file_exists($path)) {
            unlink($path);
        }

        $fp = fopen($path, 'w+');

        // Windows UTF-8 (UTF-16LE).
        //fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));

        if (defined('PHP_VERSION_ID') && PHP_VERSION_ID > 80100) {
            foreach ($matrix as $row) {
                // Compatible with PHP 8.1+.
                fputcsv($fp, $row, ";", "\"", "\\", "\r\n");
            }
        } else {
            foreach ($matrix as $row) {
                // Compatible with previous PHP versions.
                fputcsv($fp, $row, ";", "\"", "\\");
            }
        }

        fseek($fp, 0); // Same as rewind.

        $dataelements = '';

        // Windows UTF-8 (UTF-16LE).
        $dataelements .= chr(0xEF) . chr(0xBB) . chr(0xBF);

        while (($content = fgets($fp)) !== false) {
            $dataelements .= $content;
        }

        fclose($fp);

        return $dataelements;
    }

    /**
     * Exports report data, in XLSX format (UTF-8, semicolon, CRLF).
     */
    public function export_to_xlsx() {
        global $CFG;
        require_once($CFG->dirroot . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'excellib.class.php');

        $matrix = [];

        $headers = $this->reportcolumns[current_language()];

        if (!empty($headers)) {
            foreach ($headers as $key => $heading) {
                $matrix[0][$key] = $this->filter_control_chars(html_entity_decode(htmlspecialchars_decode(strip_tags(nl2br($heading)))));
            }
        }

        if (!empty($this->reportdata)) {
            $i = 0;

            foreach ($this->reportdata as $row) {
                $j = 0;

                foreach ($row as $item) {
                    $matrix[$i + 1][$j] =
                        $this->filter_control_chars(html_entity_decode(htmlspecialchars_decode(strip_tags(nl2br($item)))));
                    $j++;
                }

                $i++;
            }
        }

        $filename = strtolower(
                get_string('pluginname', 'report_clearview'))
            . '_'
            . strtolower($this->title) . '_' . (time());

        $filename = str_replace(' ', '_', $filename);

        $downloadfilename = clean_filename($filename);

        // Creating a workbook.
        $workbook = new \MoodleExcelWorkbook("-");
        // Sending HTTP headers.
        $workbook->send($downloadfilename);
        // Adding the worksheet.
        $myxls = $workbook->add_worksheet($filename);

        foreach ($matrix as $ri => $col) {
            foreach ($col as $ci => $cv) {
                $myxls->write_string($ri, $ci, $cv);
            }
        }

        $workbook->close();
        exit;
    }

    /**
     * Hydrates an array of objects to an array of arrays.
     *
     * @param array $sqlresults An array of results like those returned from the get_records_sql() function.
     *
     * @return array
     */
    protected function hydrate_to_array(array $sqlresults) {
        $results = [];

        foreach ($sqlresults as $index => $object) {
            $results[$index] = (array) $object;
        }

        return $results;
    }

    /**
     * Hydrates an iterable value object to an array of arrays.
     *
     * @param iterable $sqlresults An iterable recordset like the one returned from the get_recordset_sql() function.
     * @param bool $packkeys A flag to pack the main array of arrays.
     *
     * @return array
     */
    protected function hydrate_recordset_to_array(iterable $sqlresults, bool $packkeys = true) {
        if ($packkeys) {
            $sqlresults = $this->pack_recordset_keys($sqlresults);
        }

        return $this->hydrate_to_array($sqlresults);
    }

    /**
     * Helper method to pack an array or an SQL recordset object to a packed array.
     *
     * @param iterable $records
     *
     * @return array
     */
    protected function pack_recordset_keys(iterable $recordset) {
        $results = [];

        foreach ($recordset as $item) {
            $results[] = $item;
        }

        return $results;
    }

    protected function filter_control_chars(string $input) {
        // Strip control characters from input string.
        // preg_replace('/[\x00-\x1F\x7F]/', '', $input);
        // preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
        return preg_replace('/[[:cntrl:]]/', ' ', $input);
    }

    /**
     * Get the reportdata property.
     *
     * @return array|mixed
     */
    public function get_report_data() {
        return $this->reportdata;
    }
}
