<?php
ini_set('display_errors','1'); error_reporting(E_ALL);
date_default_timezone_set('Europe/Madrid');

include('config.php');
if(!defined('TOKEN')) {
   echo "Define a token in config.php:<br>\n<br>\n";
   echo "<pre>   define('TOKEN', '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11');</pre><br>\n<br>\n<br>\n";
}
if (!isset($_SQL)) {
   echo "Set the sql data in config.php:<br>\n<br>\n";
   echo "<pre>   \$_SQL = Array(
      'host' => 'localhost',
      'dbName' => 'corewarsbot',
      'user' => 'corewarsbot',
      'pw' => 'password'
   );</pre>";
}
if (!defined('TOKEN') || !isset($_SQL)) {
   exit();
}
require_once('db.php');


if (isset($_GET['setHook'])) {
   echo sendApiRequest('setwebhook', array('url' => $botHook));
   exit;
}

$_IN = file_get_contents("php://input");
$update = json_decode($_IN, true);

if (isset($update['inline_query'])) {
   $inline_query = $update['inline_query'];
   if (false && !in_array($inline_query['from']['id'], $admins)) { // Remove the "false && " to set maintenance mode
      $ans = array(
         'inline_query_id' => $inline_query['id'],
         'results' => json_encode(array(
            array(
               'type' => 'article',
               'id' => "1",
               'title' => 'inline mode is in maintenance for this bot!',
               'message_text' => 'This is not working yet!!!',
               'is_personal' => true,
               'cache_time' => 0
            )
         ))
      );
      $r = sendApiRequest('answerInlineQuery', $ans);
      exit;
   }
   $user = getUserById($inline_query['from']['id']);
   if ($user === false) {
         $ans = array(
         'inline_query_id' => $inline_query['id'],
         'results' => json_encode(
            array(
               array(
                  'type' => 'article',
                  'id' => "1",
                  'title' => 'I want to register!!!',
                  'message_text' => "Clic [here](https://telegram.me/corewarsbot?start=register) to register",
                  'parse_mode' => 'Markdown',
                  'is_personal' => true,
                  'cache_time' => 0
               )
            )
         )
      );
      $r = sendApiRequest('answerInlineQuery', $ans);
      exit();
   }
   
   $warriors = array();
   foreach(getAllWarriorsFromUserId($user['id'], $inline_query['query']) as $wi => $warrior) {
      array_push($warriors, array(
         'type' => 'article',
         'id' => $warrior['id'],
         'title' => $warrior['name'],
         'message_text' => 'Warrior _'.$warrior['name'].'_  by @'.getUserById($warrior['user'])['username']."\n\n```".getWarriorCodeFromId($warrior['id']).'```',
         'parse_mode' => 'Markdown',
         'is_personal' => true,
         'cache_time' => 0
      ));
   }
   $ans = array(
      'inline_query_id' => $inline_query['id'],
      'results' => json_encode(
         $warriors
      )
   );
   
   $r = sendApiRequest('answerInlineQuery', $ans);
} else if (isset($update['message'])) {
   $update = $update['message'];
   
   if (false && !in_array($update['from']['id'], $admins)) { // Remove the "false && " to set maintenance mode
      sendMsg($update['from']['id'], "Maintenance!", false);
      exit;
   }

   if (!isset($update['text'])) {
      logToFile(json_encode($update)); // Debug!
      exit;
   }
   $user = getUserById($update['from']['id']);
   if ($update['text'] == '/register' || $update['text'] == '/start register') {
      if ($user !== false) {
         sendMsg($user['id'], 'It seems you are alerady registered!', false);
      } else {
         addUserToDB(array('id' => $update['from']['id'], 'username' => $update['from']['username']));
         $user = getUserById($update['from']['id']);
         if ($user['id'] == $update['from']['id']) {
            sendMsg($user['id'], "Registered! You can now add warriors!!!\n(You are not participating. To participate, /participate )", false);
         } else {
            sendMsg($user['id'], 'Something wrong happened...', false);
         }
      }
      exit;
   }

   if ($user === false) {
      sendMsg($update['from']['id'], 'You are not registered... If you want, yo can /register', false);
      exit;
   }

   $userState = json_decode($user['state'], true);
   
   if ($update['text'] == '/cancel') {
      updateUserState($user, json_encode(array('state' => 'none')));
      sendMsg($user['id'], "Cancelling...", false);
      exit();
   }
   if ($userState['state'] == 'none') {
      if ($update['text'] == '/sendwarrior') {
         updateUserState($user, json_encode(array('state' => 'sendWarrior')));
         sendMsg($user['id'], "Warrior name:", false);
      } else if ($update['text'] == '/help') {
         sendMsg($user['id'], 
            "Once you are registered you can submit warriors with /sendwarrior\n".
            "If you want, you can /participate or /retreat for the following tournaments.\n".
            "When you participate, your fighter (choose with /choosewarrior ) will fight in the core of the next tournaments.\n".
            "Tournaments happen from time to time, you will be notified of the results.\n".
            "Full list of /commands here!\n".
            "This bot is in *alpha*!\n".
            "(No tournaments scheduled)",
         false);
      } else if ($update['text'] == '/commands') {
         sendMsg($user['id'],
            "/register - Become a member to participate\n".
            "/help - Do I have to explain what this is for?\n".
            "/sendwarrior - send a new warrior\n".
            "/getwarrior - If you want to take a look to the code of one of your warriors....\n".
            "/score - shows your score\n".
            "/participate - from now on, your choosen warrior will fight!\n".
            "/retreat - from now on, you wont participate\n".
            "/choosewarrior - choose one of your warriors for the fight!\n".
            "/deletewarrior - delete one of your warriors\n".
            "/cancel - cancels operation\n".
            "/commands - get a list of all available commands\n",
         false);
      } else if ($update['text'] == '/score') {
         sendMsg($user['id'], 'Your score is '.$user['score']);
      } else if ($update['text'] == '/participate') {
         updateUserParticipation($user, true);
         sendMsg($user['id'], "From now on, your figther *will* participate in the tournaments!\n You can add more warrior if you like... /sendwarrior", false);
      } else if ($update['text'] == '/retreat') {
         updateUserParticipation($user, false);
         sendMsg($user['id'], "From now on, your figther *will NOT* participate in the tournaments!", false);
      } else if ($update['text'] == '/getwarrior') {
         $warriorsAsKeyboard = getWarriorsFromUserAsKeyboard($user['id']);
         array_push($warriorsAsKeyboard, array('/cancel'));
         sendMsg($user['id'], "Choose a warrior (select one from the list or input its id)", $warriorsAsKeyboard);
         updateUserState($user, json_encode(array('state' => 'getWarrior')));
      } else if ($update['text'] == '/choosewarrior') {
         $warriorsAsKeyboard = getWarriorsFromUserAsKeyboard($user['id']);
         array_push($warriorsAsKeyboard, array('/cancel'));
         sendMsg($user['id'], "Choose a warrior (select one from the list or input its id)", $warriorsAsKeyboard);
         updateUserState($user, json_encode(array('state' => 'chooseWarrior')));
      } else if ($update['text'] == '/deletewarrior') {
         $warriorsAsKeyboard = getWarriorsFromUserAsKeyboard($user['id']);
         array_push($warriorsAsKeyboard, array('/cancel'));
         sendMsg($user['id'], "Choose a warrior (select one from the list or input its id)", $warriorsAsKeyboard);
         updateUserState($user, json_encode(array('state' => 'deleteWarrior')));
      } else if (explode(" ", $update['text'])[0] == '/sim') {
         if (!in_array($update['from']['id'], $admins)) {
            sendMsg($update['from']['id'], "You are not the boss", array(array('I am not the boss', 'I am not the boss'),array('I am not the boss', 'I am not the boss')));
            exit;
         }
         $c = explode(" ", $update['text']);
         $r = executeSimulation($c[1], $c[2]);
         if ($r === false) {
            echo "There was a problem<br>\n";
            sendMsg($user['id'], "There was a problem", false);
         } else {
            $w1 = explode(" ",$r[0]);
            $w2 = explode(" ",$r[1]);
            echo 'warrior 1 : '.$w1[0].' wins; '.$w1[1].' draws;'."<br>\n";
            echo 'warrior 2 : '.$w2[0].' wins; '.$w2[1].' draws;'."<br>\n";
            sendMsg($user['id'], "Simulation: \n"."w1 : ".$w1[0]." wins; ".$w1[1]." draws;\nw2 : ".$w2[0]." wins; ".$w2[1]." draws;\n", false);
         }
      }
   } else {
      if ($userState['state'] == 'sendWarrior') {
         if (empty($userState['warriorName'])) {
            // This is the warrior name!!!
            if (preg_match('/^[a-z0-9 ]+$/i', $update['text']) && strlen($update['text']) <= 16) {
               $userState['warriorName'] = $update['text'];
               updateUserState($user, json_encode($userState));
               sendMsg($user['id'], "Hum... yeah... interesting....\n Now send me your code.", false);
            } else {
               sendMsg($user['id'], "I don't like that name. Plase, use only alphanumeric (and spaces if you like). Not more than 16 chars!", false);
            }
         } else if(empty($userState['warriorId'])){
            // Warrior name choosen, here is the code of the warrior!
            $testWarriorCode = testIfCodeIsValid($update['text']);
            if ($testWarriorCode === true) {
               $warriorId = addNewWarrior($user, $userState['warriorName'], $update['text']);
               $userState['warriorId'] = $warriorId;
               updateUserState($user, json_encode($userState));
               sendMsg($user['id'], "Would you like to choose the warrior you just submitted to be your fighter? *yes* or *no* ?", array(array('yes', 'no')));
            } else {
               sendMsg($user['id'], "Something wrong happended with your warrior....\n```\n".$testWarriorCode."```\nPlease, send the code again, or /cancel", false);
            }
         } else {
            if ($update['text'] == 'yes') {
               updateFighterWarrior($user, $userState['warriorId']);
               sendMsg($user['id'], '_'.$userState['warriorName']."_ is now your fighter!!!", false);
               updateUserState($user, json_encode(array('state' => 'none')));
            } else if ($update['text'] == 'no') {
               sendMsg($user['id'], "Ok then. If you change your opinion, you can choose this warrior as your fighter with /choosewarrior", false);
               updateUserState($user, json_encode(array('state' => 'none')));
            } else {
               sendMsg($user['id'], "Am..... *yes* or *no* ?", array(array('yes', 'no')));
            }
         }
      } else if ($userState['state'] == 'getWarrior') {
         $warriorId = explode(" ", $update['text'])[0];
         if (is_numeric($warriorId)) {
            $warrior = getWarriorById($warriorId);
            if (($warrior !== false) && ($warrior['user'] == $user['id'])) {
               sendMsg($user['id'], '```'.getWarriorCodeFromId($warrior['id']).'```', false);
               updateUserState($user, json_encode(array('state' => 'none')));
               exit();
            }
         }
         $warriorsAsKeyboard = getWarriorsFromUserAsKeyboard($user['id']);
         array_push($warriorsAsKeyboard, array('/cancel'));
         sendMsg($user['id'], "Choose a valid warrior", $warriorsAsKeyboard);
      } else if ($userState['state'] == 'chooseWarrior') {
         $warriorId = explode(" ", $update['text'])[0];
         if (is_numeric($warriorId)) {
            $warrior = getWarriorById($warriorId);
            if (($warrior !== false) && ($warrior['user'] == $user['id'])) {
               updateFighterWarrior($user, $warrior['id']);
               if ($user['participate']) {
                  sendMsg($user['id'], "Updated!\n_".$warrior['name']."_  will now be your fighter, and it will fight in the next tournaments!", false);
               } else {
                  sendMsg($user['id'], "Updated!\n_".$warrior['name']."_  will now be your fighter! However, as you are not participating, it will not fight in the next tournaments.\nParhaps you would like to /participate ?", false);
               }
               updateUserState($user, json_encode(array('state' => 'none')));
               exit();
            }
         }
         $warriorsAsKeyboard = getWarriorsFromUserAsKeyboard($user['id']);
         array_push($warriorsAsKeyboard, array('/cancel'));
         sendMsg($user['id'], "Choose a valid warrior", $warriorsAsKeyboard);
      } else if ($userState['state'] == 'deleteWarrior') {
         $warriorId = explode(" ", $update['text'])[0];
         if (is_numeric($warriorId)) {
            $warrior = getWarriorById($warriorId);
            if (($warrior !== false) && ($warrior['user'] == $user['id'])) {
               if ($user['warrior'] == $warrior['id']) {
                  sendMsg($user['id'], "You can't delete your fighter! If you want to delete this warrior, choose a different fighter:\n /choosewarrior ", false);
                  updateUserState($user, json_encode(array('state' => 'none')));
               } else {
                  $warriorName = $warrior['name'];
                  // DELETE WARRIOR HERE
                  sendMsg($user['id'], '_'.$warriorName."_ has been exterminated.\n(Well, I haven't coded this yet, so .... the warrior is still alive)", false);
                  updateUserState($user, json_encode(array('state' => 'none')));
               }
               exit();
            }
         }
         $warriorsAsKeyboard = getWarriorsFromUserAsKeyboard($user['id']);
         array_push($warriorsAsKeyboard, array('/cancel'));
         sendMsg($user['id'], "Choose a valid warrior", $warriorsAsKeyboard);
      }
   }
}


function sendMsg($id, $text, $keyboard = null) {
   if ($keyboard === null) {
      sendApiRequest('sendMessage',
         array(
            'chat_id' => $id,
            'text' => $text,
            'parse_mode' => 'Markdown'
         )
      );
   } else if ($keyboard === false){
      sendApiRequest('sendMessage',
         array(
            'chat_id' => $id,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => '{"hide_keyboard":true}'
         )
      );
   } else {
      sendApiRequest('sendMessage',
         array(
            'chat_id' => $id,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => '{"keyboard":'.json_encode($keyboard).',"one_time_keyboard":true}'
         )
      );
   }
}

function sendApiRequest($method, $params = array()) {
   $curl = curl_init();
   curl_setopt_array($curl, array(
       CURLOPT_RETURNTRANSFER => 1,
       CURLOPT_URL => 'https://api.telegram.org/bot'. TOKEN . '/' . $method . '?' . http_build_query($params),
       CURLOPT_SSL_VERIFYPEER => false
   ));
   return curl_exec($curl);
}

function addUserToDB($user) {
   mkdir('./warriors/'.$user['id'].'/');
   global $db;
   $stmt = $db->prepare("INSERT INTO users(id, username) VALUES(:id, :username)");
   $stmt->bindValue(':id', $user['id'], PDO::PARAM_INT);
   $stmt->bindValue(':username', $user['username'], PDO::PARAM_STR);
   $stmt->execute();
   $db->lastInsertId();
   return $user['id'];
}

function updateUserState($user, $state) {
   global $db;
   $stmt = $db->prepare("UPDATE users SET state=:state WHERE id=:id");
   $stmt->bindValue(':state', $state, PDO::PARAM_STR);
   $stmt->bindValue(':id', $user['id'], PDO::PARAM_INT);
   $stmt->execute();
}

function updateUserParticipation($user, $participation) {
   global $db;
   $stmt = $db->prepare("UPDATE users SET participate=:participate WHERE id=:id");
   $stmt->bindValue(':participate', $participation, PDO::PARAM_INT);
   $stmt->bindValue(':id', $user['id'], PDO::PARAM_INT);
   $stmt->execute();
}

function updateFighterWarrior($user, $warriorId) {
   global $db;
   $stmt = $db->prepare("UPDATE users SET warrior=:warrior WHERE id=:id");
   $stmt->bindValue(':warrior', $warriorId, PDO::PARAM_STR);
   $stmt->bindValue(':id', $user['id'], PDO::PARAM_INT);
   $stmt->execute();
}

function getUserById($userId) {
   global $db;
   $stmt = $db->prepare("SELECT * FROM users WHERE id=?");
   $stmt->bindValue(1, $userId, PDO::PARAM_INT);
   $stmt->execute();
   if ($stmt->rowCount() == 1) {
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      if (count($rows) == 1) {
         if (isset($rows[0]['id'])) {
            $user = $rows[0];
            return $user;
         }
      } 
   }
   return false;
}

function getWarriorById($warriorId) {
   global $db;
   $stmt = $db->prepare("SELECT * FROM warriors WHERE id=?");
   $stmt->bindValue(1, $warriorId, PDO::PARAM_INT);
   $stmt->execute();
   if ($stmt->rowCount() == 1) {
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      if (count($rows) == 1) {
         if (isset($rows[0]['id'])) {
            $warrior = $rows[0];
            return $warrior;
         }
      } 
   }
   return false;
}

function getAllWarriorsFromUserId($userId, $filter = "") {
   global $db;
   $filter = '%'.$filter.'%';
   //$filter = '%';
   $stmt = $db->prepare("SELECT * FROM warriors WHERE user=? AND name LIKE ?");
   $stmt->bindValue(1, $userId, PDO::PARAM_INT);
   $stmt->bindValue(2, $filter, PDO::PARAM_STR);
   $stmt->execute();
   return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getWarriorsFromUserAsKeyboard($userId) {
   $warriors = getAllWarriorsFromUserId($userId);
   $keyboard = array();
   foreach ($warriors as $wi => $warrior) {
      array_push($keyboard, array($warrior['id'].' - '.$warrior['name']));
   }
   return $keyboard;
}

function getWarriorCodeFromId($warriorId) {
   $warrior = getWarriorById($warriorId);
   return file_get_contents('./warriors/'.$warrior['user'].'/'.$warrior['id'].'.red');
}

function executeSimulation($warrior1ID, $warrior2ID, $rounds = 1) {
   $warrior1 = getWarriorById($warrior1ID);
   $warrior2 = getWarriorById($warrior2ID);
   
   //sendMsg('43804645', 'Debug:   "./bin/pmars" -r '.$rounds.' -b -k "./warriors/'.$warrior1['user'].'/'.$warrior1['id'].'.red" "./warriors/'.$warrior2['user'].'/'.$warrior2['id'].'.red"');
   exec ('"./bin/pmars" -r '.$rounds.' -b -k "./warriors/'.$warrior1['user'].'/'.$warrior1['id'].'.red" "./warriors/'.$warrior2['user'].'/'.$warrior2['id'].'.red"', $output, $ret);
   if (! $ret == 0) return false;
   return $output;
}

function addNewWarrior($user, $warriorName, $warriorCode) {
   global $db;
   $stmt = $db->prepare("INSERT INTO warriors(user, name) VALUES(:user, :name)");
   $stmt->bindValue(':user', $user['id'], PDO::PARAM_INT);
   $stmt->bindValue(':name', $warriorName, PDO::PARAM_STR);
   $stmt->execute();
   $warriorId = $db->lastInsertId();
   
   $wFile = fopen('./warriors/'.$user['id'].'/'. $warriorId .'.red', 'w');
   fwrite($wFile, ";redcode-94b\n;assert 1\n;name ".$warriorName."\n;author ".$user['username']."\n;strategy try to win\n;date ".date('Y-M-d')."\n;version 1\n\n".strtolower($warriorCode));
   fclose($wFile);
   
   return $warriorId;
}

function logToFile($text) {
   $wFile = fopen('./log.log', 'a');
   fwrite($wFile, $text . "\n--------------------\n");
   fclose($wFile);
}

function testIfCodeIsValid($code) {
   $temp_file = tempnam(sys_get_temp_dir(), 'CWB');
   $wFile = fopen($temp_file, 'w');
   fwrite($wFile, ";redcode-94b\n;assert 1\n;name debug\n;author author\n;strategy none\n;date ".date('Y-M-d')."\n;version 1\n\n".strtolower($code));
   fclose($wFile);
   unset($output); unset($ret);
   exec ('"./bin/pmars" -b -k "'.$temp_file.'" 2>&1', $output, $ret);
   
   //print_r($output);
   
   if (count($output) !== 1) {
      unset($output[count($output) - 1]);
      return join("\n", $output);
   }
   if ($ret !== 0) {
      return join("\n", $output);
   }
   return true;
}

/*
$_IN = '{
   "update_id":1,
   "message":{
      "message_id":1,
      "from":{
         "id":43804645,
         "first_name":"DevPGSV",
         "username":"DevPGSV"
      },
      "chat":{
         "id":43804645,
         "first_name":"DevPGSV",
         "username":"DevPGSV",
         "type":"private"
      },
      "date":1,
      "text":"msg"
   }
}';
*/