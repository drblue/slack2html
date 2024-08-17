#!/usr/local/bin/php
<?php
/////////////////////
// slack2html
// by @levelsio
/////////////////////
//
/////////////////////
// WHAT DOES THIS DO?
/////////////////////
//
// Slack lets you export the chat logs (back to the first messages!), even if
// you are a free user (and have a 10,000 user limit)
//
// This is pretty useful for big chat groups (like mine, #nomads), where you
// do wanna see the logs, but can't see them within Slack
//
// Problem is that Slack exports it as JSON files, which is a bit unusable,
// so this script makes it into actual HTML files that look like Slack chats
//
///////////////////
// INSTRUCTIONS
///////////////////
//
// Run this script inside the directory of an extracted (!) Slack export zip
// e.g. "/tmp/#nomads Slack export Aug 25 2015" like this:
// MacBook-Pro:#nomads Slack export Aug 25 2015 mbp$ php slack2html.php
//
// It will then make two dirs:
// 	/slack2html/json
// 	/slack2html/html
//
// In the JSON dir it will put each channels chat log combined from all the
// daily logs that Slack outputs (e.g. /channel/2014-11-26.json)
//
// In the HTML dir it will generate HTML files with Slack's typical styling.
// It will also create an index.html that shows all channels
//
///////////////////
// FEEDBACK
///////////////////
//
// Let me know any bugs by tweeting me @levelsio
//
// Hope this helps!
//
// Pieter @levelsio
//
/////////////////////

	if ($argc == 1) {
		echo "Usage: slack2html.php <unzipped slack export> <destination for generated html>\n";
		echo "\n";
		echo " <unzipped slack export>            - path to the unzipped slack export\n";
		echo " <destination for generated files>  - where to put the generated files (optional, defaults to current dir)\n";
		echo "\n";
		exit;
	}

	if (!is_dir($argv[1])) {
		echo "Please feed me the path to your (unzipped) Slack export.\n";
		exit(1);
	}

	$slack_export_path = $argv[1];
	$destination_path = isset($argv[2]) ? $argv[2] : __DIR__;
	if (!is_dir($destination_path)) {
		$res = mkdir($destination_path);
		if (!$res) {
			echo "Could not create folder for the generated files at '{$destination_path}'.\n";
			exit(1);
		}
	}

	if (!file_exists($slack_export_path . '/users.json')) {
		echo "That path doesn't seem to have a Slack export.\n";
		exit(1);
	}

	ini_set('memory_limit', '1024M');
	date_default_timezone_set('UTC');
	mb_internal_encoding("UTF-8");
	error_reporting(E_ERROR);

	function file_get_contents_utf8($fn) {
		$content = file_get_contents($fn);
		return mb_convert_encoding($content, 'UTF-8',
			 mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true));
	}

	// <config>
		$stylesheet="
			* {
				font-family: sans-serif;
			}
			body {
				padding: 1rem;
			}
			.messages {
				width: 100%;
				max-width: 700px;
				margin-left: auto;
				margin-right: auto;
				text-align: left;
				display: block;
			}
			.messages .message-row {
				margin-top: 0.5rem;
				margin-bottom: 0.5rem;
			}
			.messages img {
				background-color: rgb(248,244,240);
				width: 36px;
				height: 36px;
				border-radius: 0.2em;
				display: inline-block;
				vertical-align: top;
				margin-right: 0.65em;
			}
			.messages .time {
				display: inline-block;
				color: rgb(200,200,200);
				margin-left: 0.5em;
			}
			.messages .username {
				display: inline-block;
				font-weight: 600;
				line-height: 1;
			}
			.messages .message {
				display: inline-block;
				vertical-align: top;
				line-height: 1;
				width: calc(100% - 3em);
			}
			.messages .message .msg {
				line-height: 1.5;
			}
		";
	// </config>

    // <compile daily logs into single channel logs>
		$files=scandir($slack_export_path);
		$jsonDir=$destination_path.'/'.'json';
		if(!is_dir($jsonDir)) mkdir($jsonDir);

		foreach($files as $channel) {
			if($channel=='.' || $channel=='..') continue;
			if(is_dir($slack_export_path.'/'.$channel)) {
				$channelJsonFile=$jsonDir.'/'.$channel.'.json';
				if(file_exists($channelJsonFile)) {
					echo "JSON already exists ".$channelJsonFile."\n";
					continue;
				}

				unset($chats);
				$chats=array();

				echo '====='."\n";
				echo 'Combining JSON files for #'.$channel."\n";
				echo '====='."\n";

				$dates=scandir($slack_export_path.'/'.$channel);
				foreach($dates as $date) {
					if(!is_dir($date)) {
						echo '.';
						$messages=json_decode(file_get_contents_utf8($slack_export_path.'/'.$channel.'/'.$date),true);
						if(empty($messages)) continue;
						foreach($messages as $message) {
							array_push($chats,$message);
						}
					}
				}
				echo "\n";

				file_put_contents($channelJsonFile,json_encode($chats));
				echo number_format(count($chats)).' messages exported to '.$channelJsonFile."\n";
			}
		}
    // </compile daily logs into single channel logs>

	// <load users file>
		$users=json_decode(file_get_contents_utf8($slack_export_path.'/'.'users.json'),true);
		$usersById=array();
		foreach($users as $user) {
			$usersById[$user['id']]=$user;
		}
	// </load users file>

	// <load channels file>
		$channels=json_decode(file_get_contents_utf8($slack_export_path.'/'.'channels.json'),true);
		$channelsById=array();
		foreach($channels as $channel) {
			$channelsById[$channel['id']]=$channel;
		}
	// </load channels file>

	// <generate html from channel logs>
		$htmlDir=$destination_path.'/'.'html';
		if(!is_dir($htmlDir)) mkdir($htmlDir);
		$channels=scandir($jsonDir);
		$channelNames=array();
		$mostRecentChannelTimestamps=array();
		foreach($channels as $channel) {
			if($channel=='.' || $channel=='..') continue;
			if(is_dir($channel)) continue;

			$mostRecentChannelTimestamp=0;
			if($message['ts']>$mostRecentChannelTimestamp) {
				$mostRecentChannelTimestamp=$message['ts'];
			}
			$array=explode('.json',$channel);
			$channelName=$array[0];

			$channelHtmlFile=$htmlDir.'/'.$channelName.'.html';
			if(file_exists($channelHtmlFile)) {
				echo "HTML already exists ".$channelHtmlFile."\n";
				continue;
			}

			array_push($channelNames,$channelName);
			echo '====='."\n";
			echo 'Generating HTML for #'.$channelName."\n";
			echo '====='."\n";
			$messages=json_decode(file_get_contents_utf8($jsonDir.'/'.$channel),true);
			if(empty($messages)) continue;
			$htmlMessages='<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta http-equiv="X-UA-Compatible" content="ie=edge"><title>#'.$channelName.'</title></head><body><style>'.$stylesheet.'</style><div class="messages">';
			foreach($messages as $message) {
				if(empty($message)) continue;
				if(empty($message['text'])) continue;
				echo '.';

				// change <@U38A3DE9> into levelsio
				if(stripos($message['text'],'<@')!==false) {
					$usersInMessage=explode('<@',$message['text']);
					foreach($usersInMessage as $userInMessage) {
						$array=explode('>',$userInMessage);
						$userHandleInBrackets=$array[0];
						$array=explode('|',$array[0]);
						$userInMessage=$array[0];
						$username=$array[1];
						if(empty($username)) {
							$username=$usersById[$userInMessage]['name'];
						}
						$message['text']=str_replace('<@'.$userHandleInBrackets.'>','@'.$username,$message['text']);
					}
				}

				// change <#U38A3DE9> into #_chiang-mai
				if(stripos($message['text'],'<#')!==false) {
					$channelsInMessage=explode('<#',$message['text']);
					foreach($channelsInMessage as $channelInMessage) {
						$array=explode('>',$channelInMessage);
						$channelHandleInBrackets=$array[0];
						$array=explode('|',$array[0]);
						$channelInMessage=$array[0];
						$channelNameInMessage=$array[1];
						if(empty($username)) {
							$channelNameInMessage=$channelsById[$channelInMessage]['name'];
						}
						if(!empty($username)) {
							$message['text']=str_replace('<#'.$channelHandleInBrackets.'>','#'.$channelNameInMessage,$message['text']);
						}
					}
				}
				// change <http://url> into link
				if(stripos($message['text'],'<http')!==false) {
					$linksInMessage=explode('<http',$message['text']);
					foreach($linksInMessage as $linkInMessage) {
						$array=explode('>',$linkInMessage);
						$linkTotalInBrackets=$array[0];
						$array=explode('|',$array[0]);
						$linkInMessage=$array[0];
						$message['text']=str_replace('<http'.$linkTotalInBrackets.'>','<a href="http'.$linkInMessage.'">http'.$linkInMessage.'</a>',$message['text']);
					}
				}

				// change @levelsio has joined the channel into
				// @levelsio\n has joined #channel
				if(stripos($message['text'],'has joined the channel')!==false) {
					$message['text']=str_replace('the channel','#'.$channelName,$message['text']);
					$message['text']=str_replace('@'.$usersById[$message['user']]['name'].' ','',$message['text']);
				}

				$array=explode('.',$message['ts']);
				$time=$array[0];

				$message['text']=utf8_decode($message['text']);

				$htmlMessage='';
				$htmlMessage.='<div class="message-row"><img src="'.$usersById[$message['user']]['profile']['image_72'].'" /><div class="message"><div class="username">'.$usersById[$message['user']]['name'].'</div><div class="time">'.date('Y-m-d H:i',$message['ts']).'</div><div class="msg">'.$message['text']."</div></div></div>\n";
				$htmlMessages.=$htmlMessage;
			}

			$htmlMessages.='</div></body></html>';
			file_put_contents($channelHtmlFile,$htmlMessages);
			$mostRecentChannelTimestamps[$channelName]=$mostRecentChannelTimestamp;
			echo "\n";
		}
		asort($mostRecentChannelTimestamps);
		$mostRecentChannelTimestamps=array_reverse($mostRecentChannelTimestamps);
	// </generate html from channel logs>

	// <make index html>
		$html='<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta http-equiv="X-UA-Compatible" content="ie=edge"></head><body><style>'.$stylesheet.'</style><div class="messages">';
		foreach($mostRecentChannelTimestamps as $channel => $timestamp) {
			$html.='<a href="./'.$channel.'.html">#'.$channel.'</a> '.date('Y-m-d H:i',$timestamp).'<br/>'."\n";
		}
		$html.='</div></body></html>';
		file_put_contents($htmlDir.'/index.html',$html);
	// </make index html>

?>
