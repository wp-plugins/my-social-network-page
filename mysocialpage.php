<?php
/*
Plugin Name: My social network page
Plugin URI: http://wordpress.com/
Description: This plugin allowed you to integrate your twitter, and last.fm account into a page, or an article on your blog.
Version: 1.1
Author: Mickael Goubin
Author URI: http://www.magic-micky.net
*/

//TODO : GESTION DES ERREURS
//TODO : Optimisation du code
//@TODO : http://gdata.youtube.com/feeds/api/users/$username/uploads?max-results=2
function imag_wp_head($unused) {
	$stylesheet= get_bloginfo('url') . '/wp-content/plugins/mytwitpage/mytwitpage.css';
	echo '<link rel="stylesheet" href="' . $stylesheet . '" type="text/css" media="screen" />';
} 
add_action('wp_head', 'imag_wp_head');

function ago($tm,$rcs = 0) { //func that transform the date into "... day/hour ago"; not written by me
	$cur_tm = time(); $dif = $cur_tm-$tm;
	$pds = array('second','minute','hour','day','week','month','year','decade');
	$lngh = array(1,60,3600,86400,604800,2630880,31570560,315705600);
	for($v = sizeof($lngh)-1; ($v >= 0)&&(($no = $dif/$lngh[$v])<=1); $v--); if($v < 0) $v = 0; $_tm = $cur_tm-($dif%$lngh[$v]);
	$no = floor($no); if($no <> 1) $pds[$v] .='s'; $x=sprintf("%d %s ",$no,$pds[$v]);
	if(($rcs == 1)&&($v >= 1)&&(($cur_tm-$_tm) > 0)) $x .= time_ago($_tm);
	return $x;
}
	
//[twitter username="mysofuckingusername" limit="0"]
function mytweetsonapage($atts) {
	function get_tweets($username, $limit) { // function that recover tweets from the url
		require_once(ABSPATH . WPINC . '/rss.php');
		//$url = url where tweets are taken from; it's a atom file
		$url = "http://twitter.com/statuses/user_timeline/$username.atom?count=$count";
		// Def of $output
		$output = "";
		//download the data of the atom link into $twitterdata
		$twitterdata = fetch_rss($url);
		$i = 0;
		for($n = 0; $n < $limit;$n++) {
			//variables used by the for
			$tweetid = $twitterdata->items[$n][id];
			$tweetname=$twitterdata->items[$n][title];
			$twitterpub=$twitterdata->items[$n][published];
			$date = date('U', strtotime($twitterpub));
			$real_date=ago($date);
			//Rewriting http:// into a link	
			$pattern='/http:\/\/([a-zA-Z0-9\/=?.]+)/';
			$replace='<a href="http://\1" class="twitter_link">http://\1</a>';
			$text = preg_replace($pattern,$replace,$tweetname);
			//Rewriting @user into a link
			$pattern1  = '/\@([a-zA-Z]+)/';
			$replace1  = '<a href="http://twitter.com/\1" class="twitter_to">@\1</a>';
			$text = preg_replace($pattern1,$replace1,$text);
			//deleting first word : our username
			$pattern2='#' . $username . ':#i';
			$replace2= ' ';
			$text = preg_replace($pattern2,$replace2,$text);
			//Rewriting #tag into a link
			$pattern3='/\#([a-zA-Z0-9]+)/';
			$replace3='<a href="http://search.twitter.com/search?q=\1" class="twitter_tag">#\1</a>';
			$text = preg_replace($pattern3,$replace3,$text);

			//Rewriting link to this tweet
			$twitterlink = preg_replace('#tag:[a-z.,A-Z0-9]+:#', '', $tweetid); 
			$output   .= '<span class="twitter_tweet">' . $text . ' <a href="' . $twitterlink . '" class="twitter_ago">' . $real_date . ' ago</a></span><br />'; 
			// Saving tweet to database
			$tweet['lastcheck'] = mktime();
			$tweet['data']    = $output;
			$tweet['rawdata']  = $twitterdata;
			update_option('lasttweet',$tweet);
			}
			return $output;
	}
	
	// default values
	extract(shortcode_atts(array(
		'username' => 'MagicMicky',
		'limit' => 5,
	), $atts));
	
	$tweet   = get_option("lasttweet");
if ($tweet['lastcheck'] < ( mktime() - 60 ) ) { // if it has been 60 sec we haven't refresh tweets
	//return all of those shit ! :D
	return get_tweets($username, $limit);
}
else {
		return $tweet['data'];
}
}
add_shortcode('twitter','mytweetsonapage');

function lastfmonapage($atts) {
	function get_lastfm($username, $limit) { // function that recover tweets from the url
		require_once(ABSPATH . WPINC . '/class-snoopy.php');
		$api="e0374073b47cd96e02a53b6fba312674"; // API key for last.fm
		//$url = url where last musics played are taken from; it's a JSON file
		$url = "http://ws.audioscrobbler.com/2.0/?method=user.getrecenttracks&user=$username&limit=$limit&format=json&api_key=$api";
		// Def of $output
		$output = "";
		$snoopy = new Snoopy; //Snoopy is the calss that will contain our data
		$result = $snoopy->fetch($url);
		if($result) { //if result = 1
			$lastfmdata =  json_decode($snoopy->results); // json file, so need wordpress decode
		}
		$i = 0;
		for($n = 0; $n < $limit;$n++) {
			//variables used
			$musicname = $lastfmdata->recenttracks->track[$n]->name;
			$musicurl = $lastfmdata->recenttracks->track[$n]->url;
			$musicartist=$lastfmdata->recenttracks->track[$n]->artist->{'#text'}; 
			$twitteralbum=$lastfmdata->recenttracks->track[$n]->album->{'#text'}; //not used, but can be implemented
			$timestamp = $lastfmdata->recenttracks->track[$n]->date->uts;
			$date=ago($timestamp);
			$output .= '<span class="music_lastfm"> <a href="' . $musicurl . '" class="music_link" >' . $musicname . '</a> - ' . $musicartist . ', <span class="music_ago">' . $date . 'ago</span> </span><br />';
			// Saving music to database
			$music['lastcheck'] = mktime();
			$music['data']    = $output;
			$music['rawdata']  = $lastfmdata;
			update_option('lastfmdata',$music);
		}
		return $output;
	}
	
	extract(shortcode_atts(array(
		'username' => 'Magic-Micky',
		'limit' => 5,
	), $atts));
		$music   = get_option("lastfmdata");
if ($music['lastcheck'] < ( mktime() - 60 ) ) { // if it has been 60 sec we haven't refresh tweets
	//return all of those shit ! :D
	return get_lastfm($username, $limit);
}
else {
		return $music['data'];
}

}
add_shortcode('lastfm','lastfmonapage');
?>
