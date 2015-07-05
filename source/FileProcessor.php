<?php
/**
 * User: mjharvey
 * Date: 7/5/15
 * Time: 13:45
 */

//  Block execution from through webserver
if ( (isset( $_SERVER['REQUEST_URI'])) &&  (preg_match("/".basename(__FILE__)."/i",$_SERVER['REQUEST_URI']) )){
  die('<< File Unavailable >>' . PHP_EOL . PHP_EOL);
}

require_once __DIR__.'/../vendor/autoload.php';

class FileProcessor {
// This processor class creates an object for GPAChecking a student number list against our daily snapshot data.
    public $fn;
    protected $file_data;

    public $file_data_ar = array();
    public $file_final_rows_ar = array();
    public $file_bad_rows_ar = array();
    public $outfile_rows_ar = array();

    protected $skip_header_lines = 1;
    protected $expected_row_fields = 3;

    protected $validate_regex = '/^([^\^<\"@\/\{\}\*\$%\?=>:\|;#]+)[\t,]([^\^<\"@\/\{\}\*\$%\?=>:\|;#]+)[\t,](\d{8})$/';

    public $con_obj;

    protected $header_ar = array("Last", "First","SpireID","GPA Term","cm2.00","cm2.50","cm2.75","cm3.00","cm3.40","sm2.00","sm2.50","sm2.75","sm3.00","sm3.40");

    public function __construct($fn) {
        /*
         * _construct($fn)
         *   Instantiates FileProcessor objects with designated source CSV file, $fn
         *   Check for existence;
         *   Returns true when source file is found.
         *   Dies when no file is present and displays message to standard out.
         *
         */

        if (file_exists($fn)){
            $this->fn = $fn;
            return true;
        } else {
    	    die ('<< Source File Not Found >>' . PHP_EOL . PHP_EOL);
        }
    }

    public function load(){
    /*
     * load()
     *   Loads file designated in constructor: $this->fn
     *   Skips header lines if configured
     *   Returns the number of rows imported into the raw data array: $this->file_data_ar
     *
     */
        $row_num = 0;
        if (($fh = fopen($this->fn, "r")) !== FALSE) {
            while (($line_ar = fgetcsv($fh, 20000, ",")) !== FALSE) {
                if ($row_num >= $this->skip_header_lines ) {

                        array_push($this->file_data_ar, $line_ar);

                }
                $row_num++;
            }

            fclose($fh);
        }
        return $row_num;
    }


    public function validate_line(&$line_ar){
        /*
         * validate_line(&$line_ar)
         *   Per-Line raw line data validator.
         *   Takes reference to each row's array of data so that the original line data can be cleaned up.
         *   Trims whitespace and quotes at beginning and ends of each field.
         *   Removes any tabs, line-endings, and various control characters from anywhere within each field.
         *   There is the possibility of column counts to vary from file to file.
         *   Student Number is always assumed to be last.
         *   If the last field (Student Number) is 9 chars, try trimming legacy '8' from beginning.
         *   Row Array is imploded into a comma separated string and run against the regex: $this->validate_regex
         *   The Regex checks that fields do not contain meta characters and that the last field is d{8}
         *   The Regex should be the place to alter the expectation of incoming field count.
         *   Also would change class property: $this->expected_row_fields
         *
         *   Returns true or false for validation
         *
         */

        // Get field count to handle variable field counts and still know where Student Number is located.
	    $num_fields = count($line_ar);

        // Clean up fields
        for ($i=0; $i<$num_fields; $i++) {
            $line_ar[$i] = rtrim ( $line_ar[$i],'"' );
            $line_ar[$i] = ltrim ( $line_ar[$i],'"' );
            $line_ar[$i] = ltrim( $line_ar[$i] );
            $line_ar[$i] = rtrim ( $line_ar[$i] );
            $line_ar[$i] = preg_replace('/\t\r\n\a\e\f\v/', '', $line_ar[$i]);
        }

        // Fix legacy leading 8's in the Student numbers
        if ( strlen( $line_ar[$num_fields - 1]) == 9) {
            $line_ar[$num_fields -1] = ltrim ( $line_ar[$num_fields - 1],'8' );
        }

        // Create string for regex evaluation and validate string with boolean result
        $line = implode(",", $line_ar);
        if (preg_match($this->validate_regex, $line)) {
            return true;
        } else {
            return false;
        }
    }

    public function validate(){
        /*
         * validate()
         *   Line iterator and output generator
         *
         *   Loops over all loaded file row arrays.
         *   Each array is sent to the validate_line(&$ar) function for validation
         *   Tallies are kept for validation passes and fails.
         *   Valid lines are pushed on to $this->file_final_rows_ar for later DB processing
         *   Invalid lines are pushed on to $this->file_bad_rows_ar for later Reporting
         *   After all lines have bee looped through, totals and Invalid rows are reported in the output.
         *
         *   Returns output text in the $out variable
         *
         */


        $passed = 0;
        $failed = 0;
        $i = null;
        $line_ar = null;
    	$data_rows = count( $this->file_data_ar);

        // Starting progress reporting text
        $out = "Value Check: " . __DIR__ . PHP_EOL . PHP_EOL . sprintf("%-32s", "Validating:");

        // Loop through loaded data row arrays and send to validate_line(&$ar) function
        for ($i=0; $i<$data_rows; $i++) {
            $line_ar = $this->file_data_ar[$i];


            // Check Number of fields and branch
            $num_fields = count($line_ar);

            if ($num_fields == $this->expected_row_fields) {
                if( $this->validate_line($line_ar)) {
                    // If valid row, push to $this->file_final_rows_ar
                    array_push( $this->file_final_rows_ar,$line_ar);  // Keep cleaned up line
                    $passed++;
                } else {
                    // If invalid row, push to $this->file_bad_rows_ar
                    array_push( $this->file_bad_rows_ar, $line_ar);
                    $failed++;
                } // End If
            } else {
                // Wrong Number of fields
                // If field counts are off from class property: $this->expected_row_fields, push to $this->file_bad_rows_ar
                array_push( $this->file_bad_rows_ar, $line_ar);
                $failed++;
            } // End If

        } // End For

        // Add totals to reporting text output
        $out .= "  [Done]" . PHP_EOL;
        $out .= "  Lines Passed: " . $passed . " out of " . ($passed + $failed) . PHP_EOL;
        $out .= "  Lines Failed: " . $failed . " out of " . ($passed + $failed) . PHP_EOL . PHP_EOL;


        // Loop over "Bad Rows" and add them into reporting output text
        for ($i=0; $i<count( $this->file_bad_rows_ar); $i++) {
            $out .= sprintf("%-32s  [BAD ROW]" . PHP_EOL, "-->" . implode(',', $this->file_bad_rows_ar[$i]) . "<--");
	    } // End For loop over all loaded rows

        $out .= PHP_EOL;

        //  Return reporting text to calling scope
        return $out;
    }

    public function translate(){
        /*
         * translate()
         *   Possible future feature to translate different row formats into a standard.
         */
            return true;
    }


    public function process(){
        /*
         * process()
         *   Loops through all validated rows to retrieve GPA data from the Student datasource
         *
         *   Generates reporting text for each row and displays status of [OK] or [Not in DB]
         *   Each query iteration ends with $my_success being true or false
         *   Singular rowsets are successes. Those $rows are pushed onto $my_results.
         *   $my_results is cleared each time and is a convention I use in rowsets of multiples
         *   If a query returns $my_success == true, the DB data is pushed onto $this->outfile_rows_ar
         *   Reporting text is added to from the DB data values, not what was in the source file.
         *   For non-matched rows, Reporting text is pulled from the source_array: $this->file_final_rows_ar
         */

	    $data_rows = count( $this->file_final_rows_ar);
        $out = "Processing Valid Data Rows:  " . $data_rows . PHP_EOL . PHP_EOL;

        // Begin to loop over all valid rows in $this->file_final_rows_ar
        for ($i=0; $i<$data_rows; $i++) {
            $num_fields = count($this->file_final_rows_ar[$i]);

            // Select statement uses FileProcessor connection object to look up each student.
            // This sql will be moved to a prepared statement and moved out of the method scope to class scope.
            $sql = "SELECT LastName, FirstName, PSID, hire_crit_term, crit1,crit2,crit3,crit4,crit5,crit6,crit7,crit8,crit9,crit10 FROM `".MODEL_STUDENT_DB."`.Student s1 WHERE s1.PSID ='". $this->file_final_rows_ar[$i][$num_fields-1] ."'";
            $my_con_obj = $this->con_obj;
            $my_results = array();
            $my_success = false;

            // This branching construction is a a variant of my multi-row result set pattern. It is a little verbose..
            if ($result_object = $my_con_obj->query( $sql ) ) {
                if($result_object->num_rows == 1) {
                    while($row = $result_object->fetch_assoc()) {
                        $my_success = true;
                        array_push($my_results , $row);
                    } // End WHILE
                } // End IF #rows == 1
                // Not capturing multiples except that they will return $my_success as false.

                $result_object->close();
            } else {
                // Nothing here yet
            } // End IF RESULTS

            if ( $my_success ) {
                // Report the positive results
                $out .= sprintf("%-16s %-16s  %d8  [OK]" . PHP_EOL, $my_results[0]['FirstName'], $my_results[0]['LastName'], $my_results[0]['PSID'] );

                // Save the good DB values to the output queue array
                array_push($this->outfile_rows_ar, $my_results[0]);

            } else {
                // Alter negative text output based on field counts.
                if ($num_fields > 2) {
                    $out .= sprintf("%-16s %-16s", $this->file_final_rows_ar[$i][1], $this->file_final_rows_ar[$i][0] );
                }
                // The Student number is the most important field and is the last field.
                $out .= sprintf("  %d8  [Not In DB]" . PHP_EOL, $this->file_final_rows_ar[$i][$num_fields-1] );
            }

        }
        $out .= PHP_EOL;

        // send the reporting text back to the calling scope
        return $out;
    }

    public function save(){
        /*
         * save()
         *   Saves the CSV file to disk with the "_DONE" modifier added to the filename
         *
         *   First checks that there are any rows to output
         *   Retrieves the "Hiring Criteria Term" from the first output row. They are all the same anyway.
         *   The output filename is built from the original $this->fn filename.
         */

        $rows = count($this->outfile_rows_ar);

        if ($rows > 0){

            // If the hiring_crit_term is not set, error is handled.
            if (isset($this->outfile_rows_ar[0]['hire_crit_term'])) {
                $hire_crit_term = $this->outfile_rows_ar[1]['hire_crit_term'];
            } else {
                $hire_crit_term = "No Data";
            }

            // Build Filename for output file
            $fn_parts = explode(".", $this->fn);
            $extension = $fn_parts[1];
            $output_fn = $fn_parts[0] . "_DONE." . $extension;

            // Open file and output CSV GPA data
            if (($fh = fopen($output_fn, "w")) !== FALSE) {

                // Add rows count and Hiring Criteria Term
                fputcsv($fh, array("GPA Check file","Rows: " . $rows),    ",", '"');
                fputcsv($fh, array("GPA Check term: " . $hire_crit_term), ",", '"');

                fputcsv($fh, $this->header_ar, ",", '"');

                // Loop over array of arrays placing the row arrays in the new CSV file
                for ($i=0;$i<$rows - 1;$i++) {

                    fputcsv($fh, $this->outfile_rows_ar[$i], ",", '"');
                }
                fclose($fh);

            } else { // Filename is not writable
                echo ("Could not open file for saving!" . PHP_EOL . PHP_EOL);
                return false;
            }

            // All done. All valid rows have been written to disk
            return true;

        } else { // No Results. No file saved.
            echo ("No Result to save to file." . PHP_EOL . PHP_EOL);
            return false;
        }

    }

}


?>
