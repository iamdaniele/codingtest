<?php
/**
 * Simple Configuration
 * 
 * Heroku uses PosgreSQL with PDO url, we use SQLite for local compatibility
 * On Heroku we must reparse the connection string
 */
// $config['db']['url'] = 'sqlite://' . realpath('data/my.db');
// $config['db']['driver'] = 'sqlite';
// 

$config = array(
  'user' => array(
    'uid' => '1470963368',
    'username' => 'test',
    'password' => '098f6bcd4621d373cade4e832627b4f6',
  ),
  'access_token_expiration' => 'now +10 years'
);

require_once 'lib/Slim/Slim.php';
require_once 'lib/db/db.class.php';

$app = new Slim();

function out($msg) {
  if (!headers_sent()) {
    header('Content-type: application/json; charset: utf-8');
  }
  echo json_encode($msg);
  exit;
}

function getAccessToken($uid) {
  global $config;
  return md5($uid) . '|' . strtotime($config['access_token_expiration']);
}

function checkAccessToken($token) {
  global $config;
  if (!$token) {
    return false;
  }
  list($uid, $expiration) = explode('|', $token);
  return ($uid && $expiration && ($uid == md5($config['user']['uid']) && time() < $expiration));
}

function sortByProximityDate($a, $b) {
  $birthday_a = explode('-', $a['birthday']);
  $birthday_b = explode('-', $b['birthday']);
  $birthday_a_time = mktime(0, 0, 0, $birthday_a[1], $birthday_a[2], date('Y'));
  $birthday_b_time = mktime(0, 0, 0, $birthday_b[1], $birthday_b[2], date('Y'));
  return ($birthday_a_time < $birthday_b_time) ? -1 : 1;
}

function idx($array, $key, $default = null) {
  return array_key_exists($key, $array) ? $array[$key] : $default;
}

$app->map('/', function () use ($app, $config) {
  out(array('error' => true, 'message' => 'Nothing to see here.'));
})->via('GET', 'POST');


$app->map('/login', function () use ($app, $config) {
  $access_token = null;
  if ($app->request()->post('username') == $config['user']['username'] &&
    $app->request()->post('password') == $config['user']['password']) {
    $access_token = getAccessToken($config['user']['uid']);
  	out(array(
  	  'uid' => $config['user']['uid'],
  	  'access_token' => $access_token,
  	));
  } else {
    out(array('error' => true, 'message' => 'Missing login data.'));
  }

})->via('POST');

$app->map('/friends', function() use ($app, $config) {
  if (!checkAccessToken($app->request()->get('access_token'))) {
    out(array('error' => true, 'message' => 'Invalid access token'));
    return;
  }
  try {
    $db = new Mongo('mongodb://fbdublin.com/codingtest');
    $collection = $db->codingtest->friends;
    // Only retrieve the first 50. It's a demo after all.
    $friends = $collection->find()->limit(50);
  } catch (Exception $e) {
    out(array('error' => true, 'message' => 'DB down.'));
  }
  $f = array();
  
  foreach ($friends as $friend) {
    $f[] = array(
      'id' => $friend['id'], 
      'name' => $friend['name'],
      'birthday' => $friend['birthday'],
      'interests' => idx($friend, 'interests', array()),
    );
  }
  uasort($f, 'sortByProximityDate');
  
  $today = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
  $idx = 0;
  foreach ($f as $u) {
    $date = explode('-', $u['birthday']);
    
    $time = mktime(0, 0, 0, $date[1], $date[2], date('Y'));
    if ($time < $today) {
      $idx++;
    } else {
      break;
    }
  }
  
  $cut = array_splice($f, $idx);
  $out = array_merge($cut, $f);

  out(array('data' => $out));
})->via('GET');

$app->run();