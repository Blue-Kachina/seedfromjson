<?php
/**
 * Created by PhpStorm.
 * User: mleering
 * Date: 2019-05-09
 * Time: 7:43 PM
 */

namespace bluekachina\seedfromjson;

use DB;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use Storage;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\ConsoleOutput;

// These are options.  You can use these constants (bitwise), so that multiple options can be used simultaneously
define('OPT_TRUNCATE_TABLE', 1);                // Determines whether or not to truncate the table connected to the model provided
define('OPT_IMPORT_DATA', 2);                   // Determines if JSON file (filename matches tablename) is to be imported from
define('OPT_SKIP_PK', 4);                       // Determines if the PK field's value is to be preserved
define('OPT_DISABLE_FK_CONSTRAINTS', 8);        // Determines if foreign key constraints should be disabled prior to importing JSON data
define('OPT_NO_SEED_IN_PROD', 16);              // Determines if table is to be populated in a development environment only
define('OPT_NO_TRUNCATE_IN_PROD', 32);          // Determines if table is to be truncated in a development environment only
define('OPT_ALWAYS_FULL_SEED', 64);             // Determines if table is to be fully seeded -- even if running a quick seed

class SeedFromJSON
{
    public $_seedingQueue;
    public $_output;
    public $_storage;

    /**
     * SeedFromJSON constructor.
     */
    public function __construct()
    {
        // Initialize properties to be used by trait methods
        $this->_seedingQueue = [];                  // This is the array that we'll be putting queued jobs into
        $this->_output = new ConsoleOutput();       // This is our output

        // Configure a storage object
        $this->_storage = Storage::disk(config('seedfromjson.STORAGE_FOLDER_NAME'));   // This is a storage container pointing to the directory where the JSON files reside

        $this->enableScreenStyles();                // This configures a few styles for us to use when outputting to the screen
    }

    // Call this function from the DatabaseSeeder in order to instruct handling of a particular model
    public function addModelToSeedingQueue($model, $options = 0, $callback_pre = null, $callback_post = null)
    {
        $instance = new $model;

        $keyname = $instance->getKeyName();
        $model = get_class($instance);
        $table = $instance->getTable();
        $filename = "{$table}.json";

        $this->_seedingQueue[] =
            compact([
                'model',
                'options',
                'table',
                'keyname',
                'filename',
                'instance',
                'callback_pre',
                'callback_post',
            ]);
    }

    // This will initiate the seeding process for all of the items that have been added to queue via addModelToSeedingQueue() function calls
    public function beginSeeding($defer=false)
    {
        if ($defer) {
            return;
        }
        $time_begin = hrtime(true);
        $this->drawHeaderToConsole();

        if (config('seedfromjson.DISABLE_ALL_FK_CONSTRAINTS')) {
            Schema::disableForeignKeyConstraints();
        }
        foreach ($this->_seedingQueue as $i => $item) {
            $this->queueItem_Truncate($item);
            $this->queueItem_Seed($item);
        }
        if (config('seedfromjson.DISABLE_ALL_FK_CONSTRAINTS')) {
            Schema::enableForeignKeyConstraints();
        }
        $time_end = hrtime(true);
        $eta = $time_end - $time_begin;
        $eta_ms = $eta/1e+6;
        $eta_s = $eta_ms / 1000;
        $this->drawFooterToConsole($eta_s);
    }

    // This function will get invoked by the beginSeeding() function
    private function queueItem_Seed($queueItem)
    {
        $message = padStringWithDots("SEED:       {$queueItem['table']}");        // Whitespacing within the string is intentional so as to allow for a table-like appearance
        $this->_output->write($message);
        if (!$this->passesEnvironmentCheck($queueItem, OPT_NO_SEED_IN_PROD)) {
            $this->writeToScreen("SKIPPED", "warning");
            return null;
        }
        if (isFlagSet($queueItem, OPT_IMPORT_DATA)) {

            if ($this->_storage->exists($queueItem['filename'])) {
                $file = $this->_storage->get($queueItem['filename']);
                $file_path = Storage::disk(config('seedfromjson.STORAGE_FOLDER_NAME'))->path($queueItem['filename']);
                $data = Items::fromFile($file_path, ['decoder' => new ExtJsonDecoder(true)]);
                $scrubbed_data = $this->prepDataForImport($queueItem, $data);

                try {
                    if (!config('seedfromjson.DISABLE_ALL_FK_CONSTRAINTS') && isFlagSet($queueItem, OPT_DISABLE_FK_CONSTRAINTS)) {
                        Schema::disableForeignKeyConstraints();
                    }

                    $this->queueItem_Callback_Pre($queueItem, $scrubbed_data);

                    // Actually Insert The Data
                    $quick = config('seedfromjson.ENABLE_QUICK_SEEDING');
                    foreach ($scrubbed_data as $scrubbed_chunk_data_index => $scrubbed_chunk_data) {
                        if (!$quick || ($quick && $scrubbed_chunk_data_index < config('seedfromjson.NUM_QUICK_RECORDS')) || isFlagSet($queueItem, OPT_ALWAYS_FULL_SEED)){
                            $queueItem['instance']::insert($scrubbed_chunk_data);
                        }
                    }

                    $this->queueItem_Callback_Post($queueItem, $scrubbed_data);

                    if (!config('seedfromjson.DISABLE_ALL_FK_CONSTRAINTS') && isFlagSet($queueItem, OPT_DISABLE_FK_CONSTRAINTS)) {
                        Schema::enableForeignKeyConstraints();
                    }

                    $this->writeToScreen("SUCCESS", "success");
                } catch (Exception $e) {
                    $msg_e = $e->getMessage();
                    $this->writeToScreen(array("FAILURE", "Unable To Insert Data - $msg_e"), "warning");
                    die();
                }
            } else {
                $this->writeToScreen(array("FAILURE", "Unable To Locate JSON Data"), "warning");
                die();
            }
        } else {
            $this->writeToScreen("SKIPPED", "warning");
        }
    }

    // This function will get invoked by the beginSeeding() function
    private function queueItem_Truncate($queueItem)
    {
        $message = padStringWithDots("TRUNCATE:   {$queueItem['table']}");
        $this->_output->write($message);
        if (!$this->passesEnvironmentCheck($queueItem, OPT_NO_TRUNCATE_IN_PROD)) {
            $this->writeToScreen("SKIPPED", "warning");
            return null;
        }
        if (isFlagSet($queueItem, OPT_TRUNCATE_TABLE)) {
            Model::unguard();
            try {
//                DB::table($queueItem['table'])->delete();
                DB::table($queueItem['table'])->truncate();
                $this->writeToScreen("SUCCESS", "success");
            } catch (Exception $e) {
                $this->writeToScreen(array("FAILURE", "Unable To Truncate Table"), "warning");
                die();
            }
            Model::reguard();
        } else {
            $this->writeToScreen("SKIPPED", "warning");
        }
    }

    // This function will get invoked by the beginSeeding() function
    private function queueItem_Callback_Pre($queueItem, $data = null)
    {
        if (!isset($queueItem['callback_pre']) || !is_callable($queueItem['callback_pre'])) {
            return;
        }
        $this->writeToScreen("\n");
        $message = padStringWithDots("PRESCRIPT:  {$queueItem['table']}");
        $this->_output->write($message);
        $this->writeToScreen("STARTED", "success");
        try {
            $queueItem['callback_pre']($data);
            $message = padStringWithDots("PRESCRIPT:  {$queueItem['table']}");
            $this->_output->write($message);
            $this->writeToScreen("SUCCESS", "success");
            $this->writeToScreen("\n");
        } catch (\RuntimeException $ex) {
            $this->writeToScreen(array("FAILURE", "Unable To Complete Callback (Pre) - {$ex->getMessage()}" ), "error");
            Log::error($ex->getMessage());
            die();
        }
    }
    private function queueItem_Callback_Post($queueItem, $data = null)
    {
        if (!isset($queueItem['callback_post']) || !is_callable($queueItem['callback_post'])) {
            return null;
        }
        $this->writeToScreen("\n");
        $message = padStringWithDots("POSTSCRIPT: {$queueItem['table']}");
        $this->_output->write($message);
        $this->writeToScreen("STARTED", "success");
        try {
            $queueItem['callback_post']($data);
            $message = padStringWithDots("POSTSCRIPT: {$queueItem['table']}");
            $this->_output->write($message);
            $this->writeToScreen("SUCCESS", "success");
            $this->writeToScreen("\n");
        } catch (\RuntimeException $ex) {
            $this->writeToScreen(array("FAILURE", "Unable To Complete Callback (Post) - {$ex->getMessage()}" ), "error");
            Log::error($ex->getMessage());
            die();
        }
    }

    private function passesEnvironmentCheck($queueItem, $option)
    {
        $option_is_active = isFlagSet($queueItem, $option);
        $current_env_is_prod = app()->environment('production');
        $test_passed = !($option_is_active && $current_env_is_prod);
        return $test_passed;
    }

    // This function will modify the data before it actually gets imported
    private function prepDataForImport($queueItem, $data)
    {
        if (!isFlagSet($queueItem, OPT_SKIP_PK)) {
            return $data;
        }
        $keyname = $queueItem['keyname'];
        if (empty($keyname)) {
            $this->writeToScreen(array("FAILURE", "Unable To Find Key Name During Scrubbing"), "warning");
            die();
        }
        foreach ($data as &$record) {
            unset($record[$keyname]);
        }

        return $data;
    }

    function formatSeconds( $seconds )
    {
        $hours = 0;
        $milliseconds = str_replace( "0.", '', $seconds - floor( $seconds ) );

        if ( $seconds > 3600 )
        {
            $hours = floor( $seconds / 3600 );
        }
        $seconds = $seconds % 3600;


        return str_pad( $hours, 2, '0', STR_PAD_LEFT )
            . gmdate( ':i:s', $seconds )
            . ($milliseconds ? ".$milliseconds" : '')
            ;
    }

    private function enableScreenStyles()
    {
        // Establish some styles to be used when outputting to console
        $this->createScreenStyle('default', 'default');
        $this->createScreenStyle('success', 'green');
        $this->createScreenStyle('warning', 'red');
        $this->createScreenStyle('warning', 'yellow');
    }

    private function drawRuntimeToConsole($time_in_seconds) {
        if (!$time_in_seconds) {
            return null;
        }
        $runtime_in_seconds = $this->formatSeconds($time_in_seconds);
        $style = "success";
        $this->_output->writeln("<{$style}>Runtime Duration:</{$style}> {$runtime_in_seconds}");
    }

    private function drawHeaderToConsole()
    {
        $this->writeToScreen('~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~');
        $this->writeToScreen('  ___|                |  ____|                        |  ___|   _ \   \  | ');
        $this->writeToScreen('\___ \   _ \  _ \  _` |  |    __| _ \  __ `__ \       |\___ \  |   |   \ | ');
        $this->writeToScreen('      |  __/  __/ (   |  __| |   (   | |   |   |  \   |      | |   | |\  | ');
        $this->writeToScreen('_____/ \___|\___|\__,_| _|  _|  \___/ _|  _|  _| \___/ _____/ \___/ _| \_| ');
        $this->writeToScreen('~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~');
        // This ASCII art is complements of https://www.ascii-art-generator.org/ -- Pretty cool that this site lets you choose various fonts (I picked shadow)
        return null;
    }

    private function drawFooterToConsole($time_in_seconds)
    {
        $this->writeToScreen('~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~');
        $this->writeToScreen('SeedFromJSON Job Complete', 'success');
        $this->drawRuntimeToConsole($time_in_seconds);
        $this->writeToScreen('~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~');
        return null;
    }

    private function createScreenStyle($style_name, $colour)
    {
        $this->_output->getFormatter()->setStyle($style_name, new OutputFormatterStyle($colour));
    }

    private function writeToScreen($message, $style = "default")
    {
        if (is_array($message)) {
            $message1 = $message[array_keys($message)[0]] ?? "";
            $message2 = $message[array_keys($message)[1]] ?? "";
        } else {
            $message1 = $message ?? "";
            $message2 = "";
        }
        $this->_output->writeln("<{$style}>{$message1}</{$style}> {$message2}");
    }

}

function isFlagSet($queueItem, $flag)
{
    return (($queueItem['options'] & $flag) == $flag);
}

function padStringWithDots($string)
{
    $num_chars_output = 50;
    $orig_string_length = strlen($string);
    $num_chars_to_generate = $num_chars_output - $orig_string_length;
    $padding = str_repeat('.', $num_chars_to_generate);
    return "{$string}<warning>{$padding}</warning>";
}
