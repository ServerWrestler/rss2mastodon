<?php

    /*

     rss2mastodon - Post an RSS or Atom feed to a Mastodon account.
     Uses PHP CLI and Composer.  No database or web server required.

     Installation:
     0.  In ~/rss2mastodon run "composer install".
     1.  See below which variables to update.
     2.  Run index.php manually in terminal or via cron.
          Example:  php ~/rss2mastodon/index.php check_user_key
     3.  Do not expose this directory to the internet or untrusted users!

     */

    // Require composer dependancies.
    require __DIR__ . '/vendor/autoload.php';

    // Change working directory to the same location as this script.
    chdir(dirname(__FILE__));

    // Verify timezone is set.  macOS issue.
    if (!ini_get('date.timezone')) {
        date_default_timezone_set('America/New_York');
    }

    // Please read through the variables below and UPDATE as required.

    // UPDATE this URL to the feed you want to follow.
    $url = "https://www.us-cert.gov/ncas/current-activity.xml";

    // UPDATE this URL to your mastodon instance.
    $mastodon_instance = "https://mastodon.technology/api/v1/statuses";

    // UPDATE to ATOM or RSS.
    $feed_type = 'RSS';

    // UPDATE to any random string without spaces.
    // It's merely to stop the script from being accidently run.
    $check_user_key = 'TEST_VALUE_1';

    // UPDATE to your instance authorization code.
    $mastodon_authcode = 'TEST_VALUE_2';

    // UPDATE hash tags you want in posts
    $mastodon_hashtags = '#TEST_VALUE_3';

    // UPDATE this value to what you would like the data file named.
    // This is used to store feed text and keep track of new articles.
    $file_prefix = 'TEST_VALUE_4';

    /*
    You shouldn't have to modify anything below this row.
    */

    /*
    Verify submitted key is correct before running script.
    */

    if (empty($argv[1])) {
      // echo error and die
      exit('Supply Check User Key' . PHP_EOL);
    } else {
      // sanatize $argv[1]
      $check_user_input = strip_tags($argv[1]);

      if ($check_user_key != $check_user_input) {
        exit('Incorrect Check User Key' . PHP_EOL);
      }
    }

    /*
    Here we will figure out what type of feed to process and call the needed functions.
    */

    switch ($feed_type) {
      case 'ATOM':
      atom_status_update();
      break;

      case 'RSS':
      rss_status_update();
      break;

      default:
      exit('Error Switch Feed Type' . PHP_EOL);
    }

    /*
    This is our main function for reading atom
    */

    function atom_status_update()
    {
      global $url;
      global $mastodon_hashtags;
      global $file_prefix;
      global $item_output;

      $atom = Feed::loadAtom($url);

      //echo htmlSpecialChars($atom->title);

      foreach ($atom->entry as $entry) {

        // create file name from title
        $title_article = $entry->title;

        // white list letters and numbers only
        $title_article_clean = preg_replace("/[^A-Za-z0-9]/",'',$title_article);
        $title_article_clean = $file_prefix . substr($title_article_clean, 0, "30") . '.txt';

        if (!file_exists($title_article_clean)) {

          $item_output = NULL;

          $fp_nodb = fopen($title_article_clean, 'w');

          $item_output = $title_article;
          $item_output .= PHP_EOL;
          $item_output .= htmlSpecialChars($entry->link['href']);
          $item_output .= PHP_EOL;
          $item_output .= date("j.n.Y H:i", (int) $entry->timestamp);
          $item_output .= PHP_EOL;
          $item_output .= 'QUOTE: '. PHP_EOL;
          $item_output .= PHP_EOL;
          $item_output .= trim(substr(strip_tags($entry->content), 0, "200"));
          $item_output .= '...' . PHP_EOL;
          $item_output .= PHP_EOL;
          $item_output .= $mastodon_hashtags;

          fwrite($fp_nodb, $item_output);

          fclose($fp_nodb);

          // Post our status update using files named $file_prefix
          mastodon_status_update();
        }
      }
      // Clean up old files.  Comment out for rarely updated sites.
      // Alternatively you can modify clean up time in the function.
      clean_up_data();
    }

    /*
    This is our main function for reading RSS
    */

    function rss_status_update()
    {
      global $url;
      global $mastodon_hashtags;
      global $file_prefix;

      $rss = Feed::loadRss($url);

      foreach ($rss->item as $entry) {

        // create file name from title
        $title_article = $entry->title;

        // white list letters and numbers only
        $title_article_clean = preg_replace("/[^A-Za-z0-9]/",'',$title_article);
        $title_article_clean = $file_prefix . substr($title_article_clean, 0, "30") . '.txt';

        if (!file_exists($title_article_clean)) {

          $item_output = NULL;

          $fp_nodb = fopen($title_article_clean, 'w');

          $item_output = $title_article;
          $item_output .= PHP_EOL;
          $item_output .= htmlSpecialChars($entry->link);
          $item_output .= PHP_EOL;
          $item_output .= date("j.n.Y H:i", (int) $entry->timestamp);
          $item_output .= PHP_EOL;
          $item_output .= 'QUOTE: '. PHP_EOL;
          $item_output .= PHP_EOL;
          $item_output .= trim(substr(strip_tags($entry->description), 0, "200"));
          $item_output .= '...' . PHP_EOL;
          $item_output .= PHP_EOL;
          $item_output .= $mastodon_hashtags;

          fwrite($fp_nodb, $item_output);

          fclose($fp_nodb);

          // Post our status update using files named $file_prefix
          mastodon_status_update();
        }
      }
      // Clean up old files.  Comment out for rarely updated sites.
      // Alternatively you can modify clean up time in the function.
      clean_up_data();
    }

    /*
    This is our main function for updating our mastodon status
    */

    function mastodon_status_update()
    {
      global $mastodon_instance;
      global $mastodon_authcode;
      global $item_output;

      // create a new cURL resource
      $ch = curl_init();

      // set URL and other appropriate options
      curl_setopt($ch, CURLOPT_URL, $mastodon_instance);

      curl_setopt($ch, CURLOPT_HEADER, 1);

      curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $mastodon_authcode) );

      curl_setopt($ch, CURLOPT_POST, 1);
      $item_output_mastodon = htmlspecialchars($item_output);
      curl_setopt($ch, CURLOPT_POSTFIELDS,
      "status=$item_output_mastodon");

      // mastodon returns success or failure html
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      // Check if any error occurred
      if(curl_exec($ch) === false) {
        echo 'Curl error: ' . curl_error($ch);
      }

      // Check if any error occurred
      if(curl_errno($ch))
      {
        echo 'Curl error: ' . curl_error($ch);
      }

      // grab URL and pass it to the browser
      curl_exec($ch);

      // close cURL resource, and free up system resources
      curl_close($ch);
    }

    /*
    This is our main function for cleaning up old data files.
    Called by other functions.  Always use last.
    */

    function clean_up_data()
    {

      global $file_prefix;
      global $old_files_delete;

      foreach (glob($file_prefix."*", GLOB_ERR) as $old_files_delete) {

        $filelastmodified = filemtime($old_files_delete);

        //30 days a month * 24 hours in a day * 3600 seconds per hour
        if ( (time() - $filelastmodified) > 60*24*3600)
            {
              unlink($old_files_delete);
            }
        }
      }

?>
