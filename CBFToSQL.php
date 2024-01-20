<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
use Log;

class CBFToSQL extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cbf:tosql {type} {provider}';
    /** To use:
    ssh vagrant
    cd to test project 
    (provider is the db name)
    e.g. php artisan cbf:totype A cq9 **/

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Formatting game things';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public static function getData($url,$header = '')
    {
        try
        {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            
            if($header == '')
            {
                $header = array('Content-Type: application/json');
            }
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            
            $response = curl_exec($ch);
            curl_close($ch);

            return $response;
        }
        catch(\Exception $e)
        {
            return '';
        }
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Bro check ur db first
        /*
        *   Type A: ['1', __('products.wm.SomeGameName')]
        *   Type B: '1' => __('products.wm.SomeGameName')
        *   Type C: 'wm.SomeGameName' => 'Full Game Name'
        */

        $provider = $this->argument("provider"); //Provider Name
        $type = $this->argument("type"); // Type

        //$data = DB::SELECT("SELECT a.id, a.gamecode, b.gamename, b.gamename_kr FROM cq9_games a LEFT JOIN cq9_games_kr b ON a.gamecode = b.gamecode"); 

        $data = DB::SELECT("SELECT * FROM yeehaw");
        //The db you pick from (I use localhost importing data from excel file)
        /*
              Example table
            -------------------
            | id | gamename   |
            -------------------
            | 1  | Fa Fa Spin |
        */

        $symbols = array("'", " ", "(", ")"); //Symbols to remove to format SomeGameName

        $formatted = "";

        foreach($data as $db)
        {   
            if($db->gamename == NULL)
            {
                continue;
            }

            $fullGameName = str_replace("'", "\'", $db->gamename);  //For those with full game name
            $gamename = strtolower(str_replace($symbols, '', $db->gamename)); //Formatting to lower text
            // $fullGameName = str_replace($symbols, '', $db->gamename_kr); //For translation;

            //The reason it's on two lines is so it will show as a new line
            switch($type)
            {
                case "A":
                $formatted .= "['$db->id', __('products.$provider.$gamename')]
                ,";
                break;

                case "B":
                $formatted .= "'$db->id' => __('products.$provider.$gamename')
                ,";
                break;

                case "C":
                $formatted .= "'$provider.$gamename' => '$fullGameName',
                ";
                break;

            }
        }



        log::debug(substr($formatted, 0, -1)); //Removes the last comma
    }
}
