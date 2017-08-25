<?php
    
    /*
     
     rss2mastodon - Post an RSS or XML feed to a Mastodon account.
     
     Note that this script is merely a starting point.  It has only been tested
     with the xml feed from US-CERT.  Read through the code below and modify as needed.
     
     Step 0.
     Get your Authorization code.
     
     Step 1.
     Add the stream you want to follow.  See variable $rss below.
     
     Step 2.
     Modify $title_article_clean file prefix.
     
     Step 3.
     Modify $item_output hash tag.
     
     Step 4.
     Put index.php in a directory and run it via cron.
     
     */
    
    
    // Used for testing on macOS.  You can comment this out.
    date_default_timezone_set('America/New_York');
    
    
/*  ###################  RSS  ###################  */
    
    
    $rss = simplexml_load_file('https://www.us-cert.gov/ncas/current-activity.xml');
    //var_dump( $rss);
    
    //$feed1 = 'title: '. $rss->channel->title;
    foreach ($rss->channel->item as $item) {
        
        // create file name from title
        $title_article = $item->title;
        
        // white list letters and numbers only
        $title_article_clean = preg_replace("/[^A-Za-z0-9]/",'',$title_article);
        $title_article_clean = 'US-CERT_' . substr($title_article_clean, 0, "30") . '.txt';
        
        // if file doesnt exist, create it
        if (!file_exists($title_article_clean)) {
            
            // see TODO 3b.  setting this to null to make sure it's nothing.
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
            
/*  ###################  Mastodon  ###################  */
            
            // create a new cURL resource
            $ch = curl_init();
            
            // set URL and other appropriate options
            curl_setopt($ch, CURLOPT_URL, "https://securitymastod.one/api/v1/statuses");
            
            curl_setopt($ch, CURLOPT_HEADER, 1);
            
            // mastodon returns success or failure html
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer your_code_here') );
            
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
