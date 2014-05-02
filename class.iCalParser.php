<?php
/**
 * This PHP-Class reads in an iCal-File (*.ics), parses it and provides both
 *   an array with its basic content and with its events parsed into a more
 *   friendly format.
 *
 * PHP Version 5
 *
 * @category Parser
 * @package  Ics-parser
 * @author   s0600204 <dev@s0600204>
 * @license  http://www.opensource.org/licenses/mit-license.php  MIT License
 * @version  0.1-alpha
 * @link     ...
 * @example  $ical = new ParsedICal('MyCal.ics');
 *           print_r( $ical->events_parsed );
 */

require_once "class.iCalReader.php";

/**
 * This is the Parsed iCal-class
 *
 * @category Parser
 * @package  Ics-parser
 * @author   s0600204 <dev@s0600204>
 * @license  http://www.opensource.org/licenses/mit-license.php  MIT License
 * @link     ...
 *
 * @param {string} filename The name of the file which should be parsed
 * @constructor
 */
class ParsedICal extends ICal
{
    
    /** 
     * Creates the iCal-Object, and parses it
     * 
     * @param {string} $filename The path to the iCal-file
     *
     * @return {boolean or nothing} Returns false if file not found
     */ 
    public function __construct($file) 
    {
        if (parent::__construct($file) == false) {
            return false;
        }
        
        $this->cal["events"] = array();
        
        foreach ($this->cal["VEVENT"] as $event) {
            
            /* Check for essential components                         */
            if (!isset($event["UID"])
                || !isset($event["DTSTAMP"])
                || !isset($event["DTSTART"]))
            {
                // Throw Warning
                // ...todo...
                // Go to next event
                continue;
            }
            
            $dtstamp = $this->iCalDateToUnixTimestamp($event["DTSTAMP"]);
            $dtstart = $this->iCalDateToUnixTimestamp($event["DTSTART"]);
            if (isset($event["DTEND"])) {
                $dtend = $this->iCalDateToUnixTimestamp($event["DTEND"]);
            } else if (isset($event['DURATION'])) {
				// we translate the duration into an explicit end datetime,
				//   this makes calculating repeats easier later (hopefully)
				$dtend = $this->timestamp_add($dtstart, substr($event['DURATION']['value'],1));
            }
            
            /* determine recurrance */
            /* RRULE / EXDATE / RECURID / RDATE / SEQUENCE */
            if (isset($event["RRULE"])) {
                $rrule = array();
                $rule = explode(";", $event["RRULE"]["value"]);
                foreach ($rule as $r) {
                    list($k, $v) = explode("=", $r);
                    switch ($k) {
                        case "UNTIL":
                            $v = $this->iCalDateToUnixTimestamp($v);
                            break;
				    }
                    $rrule[$k] = $v;
                }
            }
            
            /* Create a new event instance and set required components */
            $eventInstance = array(
                    "uid" => $event["UID"]["value"],
                    "dtstamp" => $dtstamp,
                    "dtstart" => $dtstart
                );
            
            /* Set optional components 
             * 
             * single value:
             * DONE: class / created / description / dtend / duration / geo / last-mod / location / organizer / priority / status / summary / transp / url
             * NOT DO: rrule / recurid / sequence (are parsed and so are not passed on)
             * 
             * multiple value:
             * DONE: attach / attendee / categories / comment / contact / resources
             * TODO: rstatus / related / x-prop
             * NOT DO: exdate / rdate / iana-prop
             */
            foreach ($event as $component => $record) {
                switch ($component) {
                
                case "ATTACH": // #section-3.8.1.1
                case "COMMENT": // #section-3.8.1.4
                case "CONTACT": // #section-3.8.4.2
                    $tmp = array();
                    for ($c=0; $c<count($record); $c++) {
                        $tmp[] = $record[$c]["value"];
                    }
                    $eventInstance[strtolower($component)] = $tmp;
                    break;

                case "ATTENDEE": // #section-3.8.4.1
                    $tmp = array();
                    for ($c=0; $c<count($record); $c++) {
                        $tmp[] = array_merge(
                                array("mailto" => substr($record[$c]["value"], 7)),
                                $record[$c]["params"]
                            );
                    }
                    $eventInstance["attendee"] = $tmp;
                    break;
            
                case "CATEGORIES": // #section-3.8.1.2
                case "RESOURCES": // #section-3.8.1.10
                    $tmp = array();
                    for ($c=0; $c<count($record); $c++) {
                        $tmp = array_merge($tmp, $record[$c]["value"]);
                    }
                    $eventInstance[strtolower($component)] = $tmp;
                    break;
                
                case "CLASS": // #section-3.8.1.3
                    $tmp = strtoupper($record["value"]);
                    if (in_array($tmp, array("PUBLIC", "PRIVATE", "CONFIDENTIAL")) || substr($tmp, 0, 2) == "X-") {
                        $eventInstance["class"] = $tmp;
                    }
                    break;
                
                case "CREATED": // #section-3.8.7.1
                case "LAST-MODIFIED": // #section-3.8.7.3
                    $eventInstance[strtolower($component)] = $this->iCalDateToUnixTimestamp($record);
                    break;
                
                case "DESCRIPTION": // #section-3.8.1.5
                    $description = str_replace("\\,", ",", $record["value"]);
                    $eventInstance["description"] = explode("\\n", $description);
                    break;
                
                case "DURATION": // #section-3.8.2.5
                    $eventInstance["duration"] = $record['value'];
                case "DTEND": // #section-3.8.2.2
                    $eventInstance["dtend"] = $dtend;
                    break;
                
                case "GEO": // #section-3.8.1.6
                    $tmp = explode(";", $record["value"]);
                    $eventInstance["geo"] = array(
                            "lat" => floatval($tmp[0]),
                            "lon" => floatval($tmp[1])
                        );
                    break;
                
                case "LOCATION": // #section-3.8.1.7
                case "SUMMARY": // #section-3.8.1.12
                case "URL": // #section-3.8.4.6
                    $eventInstance[strtolower($component)] = $record["value"];
                    break;
                
                case "ORGANIZER": // #section-3.8.4.3
                    $tmp = array("mailto" => substr($record["value"], 7));
                    if (isset($event["ORGANIZER"]["params"])) {
                        $tmp = array_merge($tmp, $record["params"]);
                    }
                    $eventInstance["organizer"] = $tmp;
                    break;
                
                case "PRIORITY": // #section-3.8.1.9
                    $eventInstance["priority"] = intval($record["value"]);
                    break;
                
                case "STATUS": // #section-3.8.1.11
                    // currently checks only for VEVENT valid values
                    $tmp = strtoupper($record["value"]);
                    if (in_array($tmp, array("TENTATIVE", "CONFIRMED", "CANCELLED"))) {
                        $eventInstance["status"] = $tmp;
                    }
                    break;
                
                case "TRANS": // #section-3.8.2.7
                    $tmp = strtoupper($record["value"]);
                    if (in_array($tmp, array("OPAQUE", "TRANSPARENT"))) {
                        $eventInstance["trans"] = $tmp;
                    }
                    break;
                 
                }
            }
            
            
            do {
                /* add event instance to list of parsed events */
                $this->cal["events"][] = $eventInstance;
                
                $quitLoop = true;
                /* calculate next instance of event */
                if (isset($rrule)) {
                    
                    /* effects */
                    $offset = $this->rrule_offset($rrule);
                    $dtstart = $this->timestamp_add($dtstart, $offset);
                    $dtend = $this->timestamp_add($dtend, $offset);
                    
                    /* conditionals */
                    if (isset($rrule["COUNT"])) {
                        if ($rrule["COUNT"] > 1) {
                            $rrule["COUNT"]--;
                            $quitLoop = false;
                        }
                    } else if (isset($rrule["UNTIL"])) {
                        if ($rrule["UNTIL"] >= $dtstart) {
                            $quitLoop = false;
                        }
                    } else {
                        /* If no COUNT or UNTIL, events repeat for ever. Obviously
                         *   this would cause an infinite loop, so we shall put
                         *   in an arbitrary limit.
                         * 
                         * The following value is temporary and for development
                         *   purposes. It will/should be changed later (TODO)
                         */
                        if ($dtstart < mktime(0,0,0,5,1,2015)) {
                            $quitLoop = false;
                        }
                    }
                    
                }
                
                $eventInstance['dtstart'] = $dtstart;
                $eventInstance['dtend'] = $dtend;
                
            } while (!$quitLoop);
        }
        
    
    }
    
    /**
     * Returns a multi-dimensioned array either with all parseable events, or all
     *   events in the given range.
     * 
     * Overrides parent function
     * 
     * If both $start and $end are false, all events are returned.
     * 
     * If $start is a valid Unix time but $end is equivalent to false, then the
     *   function will return all events that start after the time passed via $start
     * 
     * If $start is equivalent to false but $end is a valid Unix time, then the
     *   function will return all events that end before the time passed via $end
     *
     * If both $start and $end are valid Unix times, then the function will return
     *   all events that start after the time passed via $start and end before the
     *   time passed via $end
     * 
     * Note that this function makes use of UNIX timestamps. This might be a
     *   problem on January the 29th, 2038.
     *   http://en.wikipedia.org/wiki/Unix_time#Representing_the_number
     * 
     * @param {integer} $start Either a valid Unix timestamp or false
     * @param {integer} $end   Either a valid Unix timestamp or false
     *
     * @return {array}
     */
    public function getEvents($start = false, $end = false) 
    {
        if ($start === false && $end === false) {
            return $this->cal['events'];
        } else {
            $return = array();
            
            foreach ($this->cal['events'] as $event) {
                if (($start != false && $event["dtstart"] >= $start)
                        && ($end != false && $event["dtend"] <= $end)) {
                    $return[] = $event;
                }
            }
            return $return;
        }
    }
    
    /**
     * Calculates offset between an event and the next one implied by the rrule
     * 
     * @ return {string} The change to be undertaken on a timestamp
     */
    private function rrule_offset ($rrule)
    {
        $offset = "";
        
        // todo: the other rrule changes (interval, byseclist, ...)
        $interval = isset($rrule["INTERVAL"]) ? intval($rrule["INTERVAL"]) : 1;
        
        if (isset($rrule["FREQ"])) {
            switch ($rrule["FREQ"]) {
            case "YEARLY":
                $offset = $interval."Y";
                break;
            case "MONTHLY":
                $offset = $interval."M";
                break;
            case "WEEKLY":
                $offset = $interval."W";
                break;
            case "DAILY":
                $offset = $interval."D";
                break;
            case "HOURLY":
                $offset = "T".$interval."H";
                break;
            case "MINUTELY":
                $offset = "T".$interval."M";
                break;
            case "SECONDLY":
            default:
                $offset = "T".$interval."S";
                break;
            }
        }
        
        return $offset;
    }
    
    /** 
     * Return Unix timestamp from ical date time format 
     * 
     * @param {string} $icalDate  A Date in the format YYYYMMDD[T]HHMMSS[Z] or
     *                              YYYYMMDD[T]HHMMSS
     * @param {array}  $icalDate  Alternate permitted entry. Array with a date
     *                              and params
     * @param {string} $desiredTZ Desired timezone to translate to. Either a 
     *                              valid timezone string, 'UTC', or 'local'.
     * @param {string} $eventTZ   Timezone of the event. Ignored if you've 
     *                              passed an array to $icalDate with a TZID
     *                              value
     *
     * @return {int}
     */ 
    public function iCalDateToUnixTimestamp($icalDate, $desiredTZ = 'local', $eventTZ = '') 
    {
        if (is_array($icalDate)) {
            if (isset($icalDate['params'])
                && is_array($icalDate['params'])
                && isset($icalDate['params']['TZID']))
            {
                $eventTZ = $icalDate['params']['TZID'];
            }
            $icalDate = $icalDate['value'];
        }
        
        $desiredTZ = ($desiredTZ == "local") ? date_default_timezone_get() : $desiredTZ;
        
        // totdo: create a test to make sure desiredTZ and eventTZ are valid
        
        $icalDate = str_replace('T', '', $icalDate);

        $pattern  = '([0-9]{4})';   // 1: YYYY
        $pattern .= '([0-9]{2})';   // 2: MM
        $pattern .= '([0-9]{2})';   // 3: DD
        $pattern .= '([0-9]{0,2})'; // 4: HH
        $pattern .= '([0-9]{0,2})'; // 5: MM
        $pattern .= '([0-9]{0,2})'; // 6: SS
        preg_match('/'.$pattern.'/', $icalDate, $date); 

        // Unix timestamp can't represent dates before 1970
        if ($date[1] <= 1970) {
            return false;
        } 
        // Unix timestamps after 03:14:07 UTC 2038-01-19 might cause an overflow
        // if 32 bit integers are used.
        $timestamp = mktime((int)$date[4], 
                            (int)$date[5], 
                            (int)$date[6], 
                            (int)$date[2],
                            (int)$date[3], 
                            (int)$date[1]);
        
        /* 
         * There are 3 forms of DATETIME in the Spec:
         *  Form 1 : 'Floating time' (no 'Z', no 'TZID')
         *  Form 2 : 'UTC Absolute' ('Z' suffix, overrides 'TZID')
         *  Form 3 : 'Local Absolute' (no 'Z', 'TZID' present)
         * 
         * http://tools.ietf.org/html/rfc5545#section-3.3.5
         */
        
        if (substr($icalDate, -1) == "Z") {
            /* Offset UTC to Local Time */
            $timestamp += $this->tz_offset($desiredTZ, $timestamp);
        } else if ($eventTZ != "") {
            /* Offset between Event and UTC */
            $timestamp -= $this->tz_offset($eventTZ, $timestamp);
            if ($desiredTZ != "UTC") {
                /* Offset between a Timezone and UTC */
                $timestamp += $this->tz_offset($desiredTZ, $timestamp);
            }
        }
        
        return $timestamp;
    }
    
    /**
     * Returns the offset in seconds between a timezone and UTC, taking
     *   into account Daylight Savings Time
     * 
     * @param {string}  $tz   Timezone identifier
     * @param {integer} $time Unix time at approx. time of calculating offset
     * 
     * @return {integer}   
     */
    private function tz_offset ($tz, $time)
    {
        $tzObj = new DateTimeZone($tz);
        $tzTransitions = $tzObj->getTransitions(0, $time);
        $tzTransitions = array_pop($tzTransitions);
        return $tzTransitions['offset'];
    }
    
    /**
     * Adds a period of time onto a unix timestamp
     * 
     * @param {integer} $timestamp The timestamp to add to
     * @param {string}  $offset    The offset. Syntax must follow PHP's
     *                             DateInterval class (but without the 'P' prefix):
     *                             php.net/manual/en/dateinterval.construct.php
     * 
     * @return {integer}
     */
    private function timestamp_add ($timestamp, $offset)
    {
        $datetime = new DateTime();
        $datetime->setTimestamp($timestamp);
        $datetime->add(new DateInterval('P'.$offset));
        return $datetime->getTimestamp();
    }
}
?>
