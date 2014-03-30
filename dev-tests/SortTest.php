<?php
// Provide default, to prevent warnings about it not being set.
date_default_timezone_set("UTC");

require 'class.iCalReader.php';

?>
<DOCTYPE html>
<head>
	<meta http-equiv="Content-Type" content="text/html;charset=utf-8" >
</head>

<body>
<?php

$ical   = new ICal('../MyCal.ics');
$events = $ical->getEvents();

echo "<p><b>Current Test:</b> sort (dtstart)</p>\n\n";
echo "<hr>\n\n";

echo "<p>Before:</p>\n";
echo "<pre>\n";
echo "<b>UID\t\t\t\tDTSTAMP\t\t\tDTSTART\t\t\tDTEND</b>\n";
foreach ($events as $event) {
	echo "<i>" . substr($event['UID']['value'], 0, strpos($event['UID']['value'],'@')) . "</i>\t";
	
	echo substr($event['DTSTAMP']['value'],0,4)  . "-" .
		 substr($event['DTSTAMP']['value'],4,2)  . "-" .
		 substr($event['DTSTAMP']['value'],6,2)  . " " .
		 substr($event['DTSTAMP']['value'],9,2)  . ":" .
		 substr($event['DTSTAMP']['value'],11,2) . ":" .
		 substr($event['DTSTAMP']['value'],13,2) . "\t";
	
	echo substr($event['DTSTART']['value'],0,4)  . "-" .
		 substr($event['DTSTART']['value'],4,2)  . "-" .
		 substr($event['DTSTART']['value'],6,2)  . " " ;
	if (strlen($event['DTSTART']['value']) > 10) {
		echo substr($event['DTSTART']['value'],9,2)  . ":".
			 substr($event['DTSTART']['value'],11,2) . ":".
			 substr($event['DTSTART']['value'],13,2) . "\t";
	} else {
		echo "\t\t";
	}
	
	echo substr($event['DTEND']['value'],0,4)  . "-" .
		 substr($event['DTEND']['value'],4,2)  . "-" .
		 substr($event['DTEND']['value'],6,2)  . " " ;
	if (strlen($event['DTEND']['value']) > 10) {
		echo substr($event['DTEND']['value'],9,2)  . ":".
			 substr($event['DTEND']['value'],11,2) . ":".
			 substr($event['DTEND']['value'],13,2);
	}
	echo "\n";
}
echo "</pre>\n";

$ical->sortEvents($events);

echo "<p>After:</p>\n";
echo "<pre>\n";
echo "<b>UID\t\t\t\tDTSTAMP\t\t\tDTSTART\t\t\tDTEND</b>\n";
foreach ($events as $event) {
	echo "<i>" . substr($event['UID']['value'], 0, strpos($event['UID']['value'],'@')) . "</i>\t";
	
	echo substr($event['DTSTAMP']['value'],0,4)  . "-" .
		 substr($event['DTSTAMP']['value'],4,2)  . "-" .
		 substr($event['DTSTAMP']['value'],6,2)  . " " .
		 substr($event['DTSTAMP']['value'],9,2)  . ":" .
		 substr($event['DTSTAMP']['value'],11,2) . ":" .
		 substr($event['DTSTAMP']['value'],13,2) . "\t";
	
	echo substr($event['DTSTART']['value'],0,4)  . "-" .
		 substr($event['DTSTART']['value'],4,2)  . "-" .
		 substr($event['DTSTART']['value'],6,2)  . " " ;
	if (strlen($event['DTSTART']['value']) > 10) {
		echo substr($event['DTSTART']['value'],9,2)  . ":".
			 substr($event['DTSTART']['value'],11,2) . ":".
			 substr($event['DTSTART']['value'],13,2) . "\t";
	} else {
		echo "\t\t";
	}
	
	echo substr($event['DTEND']['value'],0,4)  . "-" .
		 substr($event['DTEND']['value'],4,2)  . "-" .
		 substr($event['DTEND']['value'],6,2)  . " " ;
	if (strlen($event['DTEND']['value']) > 10) {
		echo substr($event['DTEND']['value'],9,2)  . ":".
			 substr($event['DTEND']['value'],11,2) . ":".
			 substr($event['DTEND']['value'],13,2);
	}
	echo "\n";
}
echo "</pre>\n";

?>
</pre>
</body>
</html>
