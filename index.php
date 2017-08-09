<?php

/*  ###################  RSS  ###################  */

//  1.  I assume you have an auth code.
//  2.  Read this script and replace *YOUR*.

$rss = simplexml_load_file('https://YOUR_RSS_FEED_HERE.xml');
//var_dump( $rss);

//$feed1 = 'title: '. $rss->channel->title;
foreach ($rss->channel->item as $item) {

// create file name from title
$title_article = $item->title;

// white list letters and numbers only
$title_article_clean = preg_replace("/[^A-Za-z0-9]/",'',$title_article);
$title_article_clean = 'YOUR_FILE_PREFIX_HERE' . substr($title_article_clean, 0, "30") . '.txt';

// if file doesnt exist, create it
if (!file_exists($title_article_clean)) {

$fp_nodb = fopen($title_article_clean, 'w');
// note you may need to tweek this
$item_output = $title_article;
$item_output .= PHP_EOL;
$item_output .= $item->link;
$item_output .= PHP_EOL;
$item_output .= $item->pubDate;
$item_output .= PHP_EOL;
$item_output .= 'QUOTE: ' . PHP_EOL;
$item_output .= substr(strip_tags($item->description), 0, "200");
$item_output .= '...' . PHP_EOL;
$item_output .= '#YOUR_HASH_TAG_HERE';

fwrite($fp_nodb, $item_output);

fclose($fp_nodb);

/*  ###################  Mastodon  ###################  */

// create a new cURL resource
$ch = curl_init();

// set URL and other appropriate options
curl_setopt($ch, CURLOPT_URL, "https://YOUR_MASTODON_URL_HERE/api/v1/statuses");

curl_setopt($ch, CURLOPT_HEADER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer YOUR_AUTH_CODE_HERE') );

curl_setopt($ch, CURLOPT_POST, 1);
$item_output_mastodon = htmlspecialchars($item_output);
curl_setopt($ch, CURLOPT_POSTFIELDS,
"status=$item_output_mastodon");

// grab URL and pass it to the browser
curl_exec($ch);

// close cURL resource, and free up system resources
curl_close($ch);

}

}

?>
