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

define("APP_DB_HOST",			"localhost");
define("APP_DB_USER",			"<you user>");
define("APP_DB_CRED",			"<your pass>");
define("MODEL_STUDENT_DB",		"FakeStudentData");


class FileProcessorTest extends PHPUnit_Framework_TestCase {
    private $obj = null;
    private $fn = "source/GPA_Testing.csv";

    public function setUp() {
        if( ! $this->obj = new FileProcessor($this->fn) ) {
            echo ( "Unable to locate data file: " . $this->fn . PHP_EOL );
            echo ( "--> Ending Now." . PHP_EOL . PHP_EOL );
            exit;
        };
        if( ! $this->obj->con_obj = new mysqli(APP_DB_HOST, APP_DB_USER, APP_DB_CRED, MODEL_STUDENT_DB)  ) {
            echo ( "Unable to connect to database: " . MODEL_STUDENT_DB . PHP_EOL );
            echo ( "--> Ending Now." . PHP_EOL . PHP_EOL );
            exit;
        }

    }

    public function tearDown() {
        $this->obj = null;
    }

    public function test_construct() {
        $this->assertEquals($fn, $obj->fn);
    }

    /**
     * @depends test_construct
     */
    public function testLoad(){
        $rows = $this->obj->load();
        $this->assertEquals($rows,14 );
        return true;
    }

    /**
     * @ depends testLoad
     */
    public function testValidate_line(){

        $line_ar = array ('She%$a','Erin','12502326');
        $response = $this->obj->validate_line($line_ar);
        $this->assertFalse( $response );

        $line_ar = array ('Do lcy','Allison','11287826');
        $response = $this->obj->validate_line($line_ar);
        $this->assertTrue( $response );

        $line_ar = array ('Shea','Erin','12502326    ');
        $response = $this->obj->validate_line($line_ar);
        $this->assertTrue( $response );

        return true;
    }

    /**
     * @depends testValidate_line
     */
    public function testValidate(){
        $response = $this->obj->load();
        $response = $this->obj->validate();

        $passed_rows = count($this->file_final_rows_ar);
        $failed_rows = count($this->file_bad_rows_ar);
        $data_rows = count( $this->file_data_ar);
        $this->assertEquals( $data_rows, ($passed_rows + $failed_rows) );
        return true;
    }

/*
 * Future Method
 *
    public function testTranslate(){
        return true;
    }
*/

    /**
     * @depends testValidate
     */
    public function testProcess(){
        $this->obj->load();
        $response = $this->obj->validate();
//        $this->obj->file_final_rows_ar = array(
//            array("Harvey1","Michael1","90350388"),
//            array("Harvey2","Michael2","90351315")
//        );

        $this->obj->process();

        $final_rows = count($this->obj->file_final_rows_ar);
        $outfile_rows = count( $this->obj->outfile_rows_ar);

        $this->assertEquals( $final_rows,11 );
        $this->assertEquals( $outfile_rows,5 );

        return true;
    }

    /**
     * @depends testProcess
     */
    public function testSave(){

        $this->obj->load();
        $response = $this->obj->validate();
        $response = $this->obj->process();

        $fn_parts = explode(".", $this->fn);
        $extension = $fn_parts[1];
        $output_fn = $fn_parts[0] . "_DONE." . $extension;

        $this->obj->save();


        $this->assertTrue( ( file_exists($output_fn) && is_writeable($output_fn)) );

        return true;
    }



}
?>