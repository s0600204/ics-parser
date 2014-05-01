<?php
// Provide default, to prevent warnings about it not being set.
date_default_timezone_set("UTC");

require 'class.iCalParser.php';
$ical = new ParsedICal('rruleTest.ics');


?>
<DOCTYPE html>
<head>
	<meta http-equiv="Content-Type" content="text/html;charset=utf-8" >
</head>

<body>
<?php

$events = $ical->getEvents();

echo "<p><b>Current Test:</b> RRULE rules</p>\n\n";
echo "<hr>\n\n";

//function echoTimes ($events) {
	echo "<pre>\n";
	echo "<b>UID\t\tDTSTART\t\t\tDTEND</b>\n";
	foreach ($events as $event) {
		if (isset($event['dtstart'])) {
			$uid = $event['uid'];
			$dtstart = date("Ymd His", $event['dtstart']);
			$dtend = date("Ymd His", $event['dtend']);
		} else {
			$uid = $event['UID']['value'];
			$dtstart = $event['DTSTART']['value'];
			$dtend = $event['DTEND']['value'];
		}
		
		echo "<i>" . substr($uid, 0, strpos($uid,'@')) . "</i>\t";
		
		echo substr($dtstart,0,4)  . "-" .
			 substr($dtstart,4,2)  . "-" .
			 substr($dtstart,6,2)  . " " ;
		if (strlen($dtstart) > 10) {
			echo substr($dtstart,9,2)  . ":".
				 substr($dtstart,11,2) . ":".
				 substr($dtstart,13,2) . "\t";
		} else {
			echo "\t\t";
		}
		
		echo substr($dtend,0,4)  . "-" .
			 substr($dtend,4,2)  . "-" .
			 substr($dtend,6,2)  . " " ;
		if (strlen($dtend) > 10) {
			echo substr($dtend,9,2)  . ":".
				 substr($dtend,11,2) . ":".
				 substr($dtend,13,2);
		}
		echo "\t" . $event['description'][0];
		echo "\n";
	}
	echo "</pre>\n";
//}

?>
</pre>
</body>
</html>
