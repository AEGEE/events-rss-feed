<?php

require_once 'vendor/autoload.php';

use Suin\RSSWriter\Channel;
use Suin\RSSWriter\Feed;
use Suin\RSSWriter\Item;

// Configs for MyAEGEE retrieving.
$config = new stdClass();
$config->api_url       = "https://my.aegee.eu/api/events?limit=50&offset=0&starts=" . date('Y-m-d');
$config->cache         = __DIR__ . "/json.cache"; // make this file in same dir
$config->force_refresh = false; // dev
$config->refresh       = 60 * 60; // once an hour


function retrieve_events_from_myaegee() {

    global $config;

    if ($config->force_refresh || !file_exists($config->cache) || ((time() - filectime($config->cache)) > ($config->refresh) || 0 == filesize($config->cache))) {

        // The cache is invalid or expired, retrieving from MyAEGEE
        $ch = curl_init($config->api_url);

        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        $json = curl_exec($ch);
        curl_close($ch);

        $handle = fopen($config->cache, 'wb') or die('no fopen');

        // Put the data in cache.
        fwrite($handle, $json);
        fclose($handle);
    }
    else {
        // The data is cached.
        $json = file_get_contents($config->cache);
    }

    // If we have some data, cached or not, return it.
    if (!empty($json)) {
        $response = json_decode($json);
        return $response->data;
    }

    else {
        return FALSE;
    }
}


$feed = new Feed();
$channel = new Channel();

$channel
    ->title('AEGEE European events')
    ->description('All the european events from MyAEGEE')
    ->url('https://my.aegee.eu/calendar')
    ->language('en-US')
    ->lastBuildDate(time())
    ->ttl(60)
    ->appendTo($feed);

$events = retrieve_events_from_myaegee();


foreach ($events as $event) {

    // Skip events not open for application
    if ($event->application_status == 'closed') continue;

    $item = new Item();

    $custom_description = '';

    $bodies = [];
    foreach ($event->organizing_bodies as $body) {
        $bodies[] = $body->body_name;
    }

    //if (!empty($event->image)) {
    //    $custom_description .= '<img src="https://my.aegee.eu/media/events/headimages/' . $event->image . '"/>';
    //}

    $bodies_string = join(' and ', array_filter(array_merge(array(join(', ', array_slice($bodies, 0, -1))), array_slice($bodies, -1)), 'strlen'));

    $custom_description .= "<br/>\n";
    $custom_description .= '<p>▶ Event type: <strong>' . strtoupper($event->type) ."</strong></p><br/>\n";
    $custom_description .= '<p>🌐 Organized by <strong>' . $bodies_string . "</strong></p><br/>\n";
    $custom_description .= '<p>📆 From <strong>' . date('j F Y', strtotime($event->starts))  . '</strong> to <strong>' . date('j F Y', strtotime($event->ends)) . "</strong></p><br/>\n";
    $custom_description .= '<p>⏰ Apply from <strong>' . date('j F Y', strtotime($event->application_starts))  . '</strong> to <strong>' . date('j F Y', strtotime($event->application_ends)) . "</strong></p><br/>\n";
    $custom_description .= '<p>👫 Participants: <strong>' . $event->max_participants  . "</strong></p><br/>\n";
    $custom_description .= '<p>💰 Fee: <strong>' . $event->fee  . " €</strong></p>\n";

    $item
        ->title($event->name)
        ->description($custom_description)
        //->contentEncoded($custom_description)
        ->url('https://my.aegee.eu/events/' . $event->url)
        ->creator('MyAEGEE')
        ->pubDate(strtotime($event->created_at))
        ->guid('https://my.aegee.eu/events/' . $event->id, TRUE)
        ->preferCdata(TRUE) // By this, title and description become CDATA wrapped HTML.
        ->appendTo($channel);

}

header("Content-type: application/xml");

echo $feed->render();