<?php

function dbConnect($host, $dbName, $user, $pw) {
   try {
      $db = new PDO("mysql:host=$host;dbname=$dbName;charset=utf8", $user, $pw);
      $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
      $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE , PDO::FETCH_ASSOC);
   } catch (PDOException $e) {
      echo $e->getMessage();
      $db = false;
      exit();
   }
   return $db;
}

$db = dbConnect($_SQL['host'], $_SQL['dbName'], $_SQL['user'], $_SQL['pw']);
