<?php

    /*

     rss2mastodon - Post an RSS or Atom feed to a Mastodon account.
     Uses PHP CLI and Composer.  No database or web server required.

     Installation:
     0.  In ~/rss2mastodon run "composer install".
     1.  See below which variables to update.
     2.  Run index.php manually in terminal or via cron.
          Example:  php ~/rss2mastodon/index.php check_user_key
     3.  Do not expose this directory to the internet.

     */

    // Require composer dependencies.
    require __DIR__ . '/vendor/autoload.php';

    // Verify timezone is set.  macOS issue.
    if (!ini_get('date.timezone')) {
        date_default_timezone_set('America/New_York');
    }

    // Please read through the variables below and UPDATE as required.

    // UPDATE this URL to the feed you want to follow.
    $r2m_url = "https://www.us-cert.gov/ncas/current-activity.xml";

    // UPDATE this URL to your mastodon instance.
    $r2m_mastodon_instance = "https://mastodon.technology/api/v1/statuses";

    // UPDATE to ATOM or RSS.
    $r2m_feed_type = 'RSS';

    // UPDATE to any random string without spaces.
    // It's merely to stop the script from being accidentally run.
    $r2m_check_user_key = 'TEST_VALUE_1';

    // UPDATE to your instance authorization code.
    $r2m_mastodon_authcode = 'TEST_VALUE_2';

    // UPDATE hash tags you want in posts
    $r2m_mastodon_hashtags = '#TEST_VALUE_3';

    // UPDATE this value to what you would like the data file named.
    // This is used to store feed text and keep track of new articles.
    $r2m_file_prefix = 'TEST_VALUE_4';

    // UPDATE this to the path you will store your data files in.
    // chdir(dirname(__FILE__));
    $r2m_working_directory = '/TEST/VALUE/5';
    chdir($r2m_working_directory);

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
      // sanatize $r2m_argv[1]
      $r2m_check_user_input = strip_tags($argv[1]);

      if ($r2m_check_user_key != $r2m_check_user_input) {
        exit('Incorrect Check User Key' . PHP_EOL);
      }
    }

    /*
    Here we will figure out what type of feed to process and call the needed functions.
    */

    switch ($r2m_feed_type) {
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
      global $r2m_url;
      global $r2m_mastodon_hashtags;
      global $r2m_file_prefix;
      global $r2m_item_output;

      $r2m_atom = Feed::loadAtom($r2m_url);

      //echo htmlSpecialChars($r2m_atom->title);

      foreach ($r2m_atom->entry as $r2m_entry) {

        // create file name from title
        $r2m_title_article = $r2m_entry->title;

        // white list letters and numbers only
        $r2m_title_article_clean = preg_replace("/[^A-Za-z0-9]/",'',$r2m_title_article);
        $r2m_title_article_clean = $r2m_file_prefix . substr($r2m_title_article_clean, 0, "30") . '.txt';

        if (!file_exists($r2m_title_article_clean)) {

          $r2m_item_output = NULL;

          $r2m_fp_nodb = fopen($r2m_title_article_clean, 'w');

          $r2m_item_output = $r2m_title_article;
          $r2m_item_output .= PHP_EOL;
          $r2m_item_output .= htmlSpecialChars($r2m_entry->link['href']);
          $r2m_item_output .= PHP_EOL;
          $r2m_item_output .= date("j.n.Y H:i", (int) $r2m_entry->timestamp);
          $r2m_item_output .= PHP_EOL;
          $r2m_item_output .= 'QUOTE: '. PHP_EOL;
          $r2m_item_output .= PHP_EOL;
          $r2m_item_output .= trim(substr(strip_tags($r2m_entry->content), 0, "200"));
          $r2m_item_output .= '...' . PHP_EOL;
          $r2m_item_output .= PHP_EOL;
          $r2m_item_output .= $r2m_mastodon_hashtags;

          fwrite($r2m_fp_nodb, $r2m_item_output);

          fclose($r2m_fp_nodb);

          // Post our status update using files named $r2m_file_prefix
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
      global $r2m_url;
      global $r2m_mastodon_hashtags;
      global $r2m_file_prefix;

      $r2m_rss = Feed::loadRss($r2m_url);

      foreach ($r2m_rss->item as $r2m_entry) {

        // create file name from title
        $r2m_title_article = $r2m_entry->title;

        // white list letters and numbers only
        $r2m_title_article_clean = preg_replace("/[^A-Za-z0-9]/",'',$r2m_title_article);
        $r2m_title_article_clean = $r2m_file_prefix . substr($r2m_title_article_clean, 0, "30") . '.txt';

        if (!file_exists($r2m_title_article_clean)) {

          $r2m_item_output = NULL;

          $r2m_fp_nodb = fopen($r2m_title_article_clean, 'w');

          $r2m_item_output = $r2m_title_article;
          $r2m_item_output .= PHP_EOL;
          $r2m_item_output .= htmlSpecialChars($r2m_entry->link);
          $r2m_item_output .= PHP_EOL;
          $r2m_item_output .= date("j.n.Y H:i", (int) $r2m_entry->timestamp);
          $r2m_item_output .= PHP_EOL;
          $r2m_item_output .= 'QUOTE: '. PHP_EOL;
          $r2m_item_output .= PHP_EOL;
          $r2m_item_output .= trim(substr(strip_tags($r2m_entry->description), 0, "200"));
          $r2m_item_output .= '...' . PHP_EOL;
          $r2m_item_output .= PHP_EOL;
          $r2m_item_output .= $r2m_mastodon_hashtags;

          fwrite($r2m_fp_nodb, $r2m_item_output);

          fclose($r2m_fp_nodb);

          // Post our status update using files named $r2m_file_prefix
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
      global $r2m_mastodon_instance;
      global $r2m_mastodon_authcode;
      global $r2m_item_output;

      // create a new cURL resource
      $r2m_ch = curl_init();

      // set URL and other appropriate options
      curl_setopt($r2m_ch, CURLOPT_URL, $r2m_mastodon_instance);

      curl_setopt($r2m_ch, CURLOPT_HEADER, 1);

      curl_setopt($r2m_ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $r2m_mastodon_authcode) );

      curl_setopt($r2m_ch, CURLOPT_POST, 1);
      //white list allowable chars
      $r2m_item_output_mastodon = preg_replace("/[^A-Za-z0-9\.\:\#\s\/\-\_\+\!\*]/",'',$r2m_item_output);
      curl_setopt($r2m_ch, CURLOPT_POSTFIELDS,
      "status=$r2m_item_output_mastodon");

      // mastodon returns success or failure html
      curl_setopt($r2m_ch, CURLOPT_RETURNTRANSFER, 1);
      // Check if any error occurred
      if(curl_exec($r2m_ch) === false) {
        echo 'Curl error: ' . curl_error($r2m_ch);
      }

      // close cURL resource, and free up system resources
      curl_close($r2m_ch);

    }

    /*
    This is our main function for cleaning up old data files.
    Called by other functions.  Always use last.
    */

    function clean_up_data()
    {

      global $r2m_file_prefix;
      global $r2m_old_files_delete;

      foreach (glob($r2m_file_prefix."*", GLOB_ERR) as $r2m_old_files_delete) {

        $r2m_filelastmodified = filemtime($r2m_old_files_delete);

        //30 days a month * 24 hours in a day * 3600 seconds per hour
        if ( (time() - $r2m_filelastmodified) > 60*24*3600)
            {
              unlink($r2m_old_files_delete);
            }
        }
      }

?>
