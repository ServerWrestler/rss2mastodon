<?php
    
    /*
     
     rss2mastodon - An example on how to post an RSS or XML feed 
     to a Mastodon account.  Uses PHP CLI.  No database required.
     
     Installation:
     0.  Get your Authorization code and replace CHANGE_THIS_VALUE.
     1.  Add the stream you want to follow.  See variable $rss below.
     2.  Modify variable $title_article_clean file prefix.
     3.  Modify variable $item_output hash tag.
     4.  Modify variable $mastodon_instance.
     5.  Put index.php in a directory by itself!
     6.  Run manually or via cron.
     
     Notes:
     0. This script is merely a starting point.  It has only been tested
     with the xml feed from US-CERT on macOS.
     1. Invalid XML will require code modification.  Such as Atom URLs.
     2. Data is stored in sanatized text files and deleted when over 60 days old.
     3. TODOs are listed via comments in code below.
     4. Consider using SimpliePie if more features are needed.

     */
    
    
    // Used for testing on macOS.
    date_default_timezone_set('America/New_York');
    
    
/*  ###################  RSS  ################### */
    
    $rss = simplexml_load_file('https://www.us-cert.gov/ncas/current-activity.xml');
    
    foreach ($rss->channel->item as $item) {
        
        // create file name from title
        $title_article = $item->title;
        
        // white list letters and numbers only
        $title_article_clean = preg_replace("/[^A-Za-z0-9]/",'',$title_article);
        $title_article_clean = 'US-CERT_' . substr($title_article_clean, 0, "30") . '.txt';
        
        // if file doesnt exist, create it
        if (!file_exists($title_article_clean)) {
            
            $item_output = NULL;
            
            $fp_nodb = fopen($title_article_clean, 'w');
            
            $item_output = $title_article;
            $item_output .= PHP_EOL;
            $item_output .= $item->link;
            $item_output .= PHP_EOL;
            $item_output .= $item->pubDate;
            $item_output .= PHP_EOL;
            $item_output .= 'QUOTE: ' . PHP_EOL;
            $item_output .= substr(strip_tags($item->description), 0, "200");
            $item_output .= '...' . PHP_EOL;
            $item_output .= '#USCERT';
            
            fwrite($fp_nodb, $item_output);
            
            fclose($fp_nodb);
            
/*  ###################  Mastodon  ################### */
            
            // create a new cURL resource
            $ch = curl_init();
            
            // set URL and other appropriate options
            $mastodon_instance = "https://securitymastod.one/api/v1/statuses";
            curl_setopt($ch, CURLOPT_URL, $mastodon_instance);
            
            curl_setopt($ch, CURLOPT_HEADER, 1);
            
            // mastodon returns success or failure html
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer CHANGE_THIS_VALUE') );
            
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
    
/*  ###################  Clean up old data files ###################  */
    
    // if a feed rarely posts you can comment this section out
    
    $path = getcwd().'/';
    
    if ($handle = opendir($path)) {
        
        while (false !== ($file = readdir($handle))) {
            $filelastmodified = filemtime($path . $file);
            //30 days a month * 24 hours in a day * 3600 seconds per hour
            if( ((time() - $filelastmodified) > 60*24*3600) AND ($file != 'index.php') )
            {
                unlink($path . $file);
            }
            
        }
        
        closedir($handle); 
    }
     
?>
