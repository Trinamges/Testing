<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Helper;

use Log;
use DB;
use DateTime;

class EvoStreaming extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'evo:Streaming';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Streams detailed data for Evo transactions!';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */


    public function handle()
    {
        Log::debug('Cron run : EvoStreaming START');

        try
        {
            $url = "https://gammixkplay.uat1.evo-test.com";
            $url .= '/api/streaming/game/v1/';
            $user = "gammixkplay00001";
            $password = "test123";

            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_USERPWD, $user.':'.$password);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, array($this, "process_function"));
            
            $response = curl_exec($ch);
            curl_close($ch);
        }
        catch(\Exception $e)
        {
            Log::info('Cron execution : EvoStreaming ERROR -' .$e);
            $gotCronErr = true;
        }
    }

    public function process_function($ch, $data)
    { 
        /**
        *   Updated to handle only baccarat, and only for pushed amount (tie result)
        */
        if (($data === false) || ($data == null))
        {
           throw new Exception (curl_error($curl) . " " . curl_errno($curl));
        }
        $length = strlen($data);
        if ($length > 2)
        {
            //Add new baccarat named games
            $arrGameType = ['baccarat', 'rng-baccarat'];    //these game types are evo's data, so hardcoded here for checking later

            //Member's bet type - member place bet on which one
            //we have more bet type like bankerPair,playerPair etc, but we just need either Banker/Player to get "pushed" condition
            $arrBetType = ['BAC_Player', 'BAC_Banker']; 

            $prdId = 1;

            //Kenny: I dunno why you define global variable in here, lets discuss in future.
            //Trying to make the seperated transaction into a full transaction
            global $fullTransaction;
            global $time;
            global $txnId;

            // If it's empty, it's the start of the transaction
            if($fullTransaction == '')
            {
                $time = time();
                $fullTransaction = $data;
            }
            else
            {
                // If the time is different, it is a different transaction
                if($time !== time())
                {
                    $time = time();
                    $fullTransaction = $data;
                }
                else
                {
                    // If the time is the same, it's the same transaction, and join it together
                    $fullTransaction .= $data;
                }
            }

            //Check if JSON is valid, if it's valid, the transaction is complete
            $json = json_decode($fullTransaction, true);
            if (json_last_error() === JSON_ERROR_NONE) 
            {
                log::debug($json);
                // JSON is valid
                $roundId = $json['data']['id'] ?? '';
                $users = $json['data']['participants'] ?? '';
                $gameType = $json['data']['gameType'] ?? '';
                $outcome = $json['data']['result']['outcome'] ?? ''; // Check if its Tie/Banker/Player

                if(in_array($gameType, $arrGameType))
                {
                    foreach($users as $user)
                    {
                        $totalPushedAmt = 0;
                        foreach($user['bets'] as $bet)
                        {
                            //Get the payout and stake and compare, if its the same, its a push
                            //Comparing the game code (which is member's bet type) to match with push conditions
                            if($outcome == "Tie" && $bet['payout'] == $bet['stake'] && in_array($bet['code'], $arrBetType))
                            {
                                //Gets the totalpushed for that user in case they bet on both player/banker     //Kenny: i don't understand this comment...
                                $totalPushedAmt += $bet['payout']; 
                            }
                            $txnId = $bet['transactionId'];
                        }

                        DB::UPDATE("UPDATE pushed_transactions SET pushed_amount = ? WHERE txn_id = ? AND prd_id = ?", [$totalPushedAmt,$txnId,$prdId]);
                    }
                }

               
            }
        }
        flush();
        return $length;
    } 

}