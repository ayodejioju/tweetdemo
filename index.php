<?php
/*
 *  Simple full-stack web prototype for building a Twitter clone.
 */

require_once 'db.php';
#$dbhost = 'localhost';
#$dbuser = 'root';
#$dbpass = '<password>';
#$dbname = 'tweetlead';

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

function query($query) {
  global $conn;
  $result = mysqli_query($conn, $query);
  if (!$result) {
    # print mysqli_error($conn) . $query;
    return -1;
  }
  return $result;
}

function getSingle($query) {
  global $conn;
  $result = query($query);
  $row = mysqli_fetch_row($result);
  return $row[0];
}

function getUid(){
  global $conn;
  $ip = mysqli_real_escape_string($conn, $_SERVER['REMOTE_ADDR']);
  $uid = getSingle("select uid from users where ip = '".$ip."'");
  if (!$uid) {
    query("insert into users(ip) values ('$ip')");
  }
  $uid = getSingle("select uid from users where ip = '".$ip."'");
  return $uid;
}

function renderTweets($tweets){
  global $user;
  print "<table  bordercolor=#ddd cellpadding=2 border=1>";
  foreach($tweets as $row){
    $uid = $row['uid'];
    $post = htmlspecialchars($row['post']);
    $date = $row['date'];
    
    if (!getSingle("select follower from follows where uid=$user and follower=$uid"))
      $follow = <<<EOF
	<a href=index.php?follow=$uid>Follow</a>
EOF;
    else {
      $follow = "<a href=index.php?unfollow=$uid>Unfollow</a>";
    }
    print <<<EOF
      <tr><TD>$uid</td><td width=100%><div style='max-width:500px;overflow:hidden'>$post</div></td><td nowrap>$date</td><td>$follow</td></tr>
EOF;
  }
  print "</table>";
}

$user = getUid();
$banned = array();
if(in_array($user, $banned)) die(); # banned users

# Event Handlers
if ($_REQUEST['follow']){
  $follow = intval($_REQUEST['follow']);
  query("insert ignore into follows(uid, follower) values ($user, '$follow')");
}

if ($_REQUEST['unfollow']){
  $unfollow = intval($_REQUEST['unfollow']);
  query("delete from follows where uid=$user and follower='$unfollow'");
}

if($_REQUEST['tweet']) {
  $tweet = mysqli_real_escape_string($conn, $_REQUEST['tweet']);
  if(getSingle("select count(*) from tweets where post = '$tweet' and date >= '".Date("Y-m-d H:i:s", time()-60*60)."'")){
    die("Disallowed.");
  }
  if(getSingle("select count(*) from tweets where uid=$user and date >= '".Date("Y-m-d H:i:s", time()-60*10)."'") >= 5){
    # Rate limit to less than 5 tweets in 10 minutes.
    die("Please slow down, you are tweeting too fast.");
  }
  if(preg_match('#^\d\d\d\d\d+$#', $tweet)) die("Please type a message.");
  if(preg_match('#http#i', $tweet)) die("No URLs allowed.");
  if(preg_match('#(\.io|\.com|shit|kill|eagle|nigg|fuck|.org|卐|igger|卍|licky|𝗅𝗂𝖼𝗄𝗒.𝗈𝗋𝗀)#i', $tweet)) die("Disallowed.");
  if(preg_match('#l.*i.*c.*k.*y.*o.*r.*g#i', $tweet)) die("Disallowed.");
  if(preg_match('#s.*h.*i.*t.*t.*y.*b.*o.*a.*r.*d#i', $tweet)) die("Disallowed.");

  $date = Date("Y-m-d H:i:s");
  query("insert into tweets(uid, post, date) values($user, '$tweet', '$date')");
}

# Body
print <<<EOF
<html><head>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>

<style>
body{
  padding:20px;
}
</style>
<title>The TweetDemo</title>
</head>
<body>

<h2>Twitter Prototype Demo <span class=h4>(in 140 lines of code)</span></h2>
<p class="text-secondary">

Prototype code for a full-stack web development on LAMP stack (Linux, Apache, MySQL/MariaDB, PHP).<BR>
Here is the YouTube Video Tutorial <a target=_blank href=https://www.youtube.com/watch?v=1YXqXPWjmKk>https://www.youtube.com/watch?v=1YXqXPWjmKk</a>.<BR>
<BR>
  
I teach coding at<B> <a href=http://coderpro.com/>CoderPro</a></B> if you want to learn more.<BR><BR>

  Source Code:<a target=_blank href=source.php>view source</a>

</P>
	   
EOF;
	   
die();

print <<<EOF
<form action=index.php method=post>
  <table><TR><TD width=400>
  <textarea name=tweet class="form-control" id="exampleFormControlTextarea1" rows="3"></textarea>
  </td><TD>
  <button type="submit" class="btn btn-primary">Tweet</button>
  </td></tr></table>
</form>
EOF;

print "<h4>Latest</h4>";
$result = query("select * from tweets where uid not in (".implode(",", $banned).") order by date desc limit 100");
	   
$tweets = array();
while($row = mysqli_fetch_assoc($result)){
  $tweets[] = $row;
}
renderTweets($tweets);

print "<HR>";

print "<h4>Followed users</h4>";
$tweets=array();
$result = query("select * from tweets where uid in (select follower from follows where uid=$user) order by date desc limit 100");
while($row = mysqli_fetch_assoc($result)){
  $tweets[] = $row;
}
renderTweets($tweets);
