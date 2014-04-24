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
            
            $dtstart = $this->iCalDateToUnixTimestamp($event["DTSTART"]);
            $dtend = $this->iCalDateToUnixTimestamp($event["DTEND"]);
            
            /* determine recurrance */
            if (isset($event["RRULE"])) {
                $rrule = array();
                $rule = explode(";", $event["RRULE"]["value"]);
                foreach ($rule as $r) {
                    list($k, $v) = explode("=", $r);
                    $rrule[$k] = $v;
                }
            }
            
            do {
                // todo: check that all critical components are present before adding them to an array
                //       if they are not, throw a warning and skip this event
                $this->cal["events"][] = array(
                        "uid" => $event["UID"]["value"],
                        "summary" => $event["SUMMARY"]["value"],
                        "description" => $event["DESCRIPTION"]["value"],
                        "dtstart" => $dtstart,
                        "dtend" => $dtend
                    );
                
                // todo: check for and add optional components
                
                if (isset($rrule)) {
                    $quitLoop = false;
                    /* conditionals */
                    if (isset($rrule["COUNT"])) {
                        if ($rrule["COUNT"] == 1) {
                            $quitLoop = true;
                        } else {
                            $rrule["COUNT"]--;
                        }
                    }
                    if (isset($rrule["UNTIL"])) {
                        // todo: relevant code
                    }
                    
                    /* effects */
                    $offset = $this->rrule_offset($rrule);
                    $dtstart = $this->timestamp_add($dtstart, $offset);
                    $dtend = $this->timestamp_add($dtend, $offset);
                } else {
                    $quitLoop = true;
                }
                
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
                if (($start == false || $event["dtstart"] >= $start)
                        && ($end == false || $event["dtend"] <= $end)) {
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
        
        if (isset($rrule["FREQ"])) {
            switch ($rrule["FREQ"]) {
            case "YEARLY":
                $offset = "1Y";
                break;
            case "MONTHLY":
                $offset = "1M";
                break;
            case "WEEKLY":
                $offset = "1W";
                break;
            case "DAILY":
                $offset = "1D";
                break;
            case "HOURLY":
                $offset = "T1H";
                break;
            case "MINUTELY":
                $offset = "T1M";
                break;
            case "SECONDLY":
            default:
                $offset = "T1S";
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
        
        preg_match($this::$_iso8601pattern, $icalDate, $date); 

        // Unix timestamp can't represent dates before 1970
        if ($date[1] <= 1970) {
            return false;
        } 
        // Unix timestamps after 03:14:07 UTC 2038-01-19 might cause an overflow
        // if 32 bit integers are used.
        $timestamp = mktime((int)$date[5], 
                            (int)$date[6], 
                            (int)$date[7], 
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
    
    /**
     * Sorts an array of events
     * 
     * Overrides parent function
     * 
     * This is a more accurate sort than that of its parent as it compares
     * Unix-times. However, it does have a potential problem with events that
     * fall after a particular date in 2038 that its parent does not.
     *
     * @param {array} &$events   An array with events.
     * @param {array} $sortKey   Which date-time to sort by (DTSTART, DTEND, DTSTAMP)
     * @param {array} $sortOrder Either SORT_ASC or SORT_DESC
     */
    public function sortEvents (&$events, $sortKey = "DTSTART", $sortOrder = SORT_ASC)
    {
        if ($sortOrder !== SORT_ASC && $sortOrder !== SORT_DESC) {
            // todo: set error
            return;
        }
        
        $evDTstamp = array();
        foreach ($events as $event) {
            switch ($sortKey) {
            case "DTSTAMP":
                $dt = $event["dtstamp"];
                break;
            case "DTEND":
                if (isset($event["dtend"])) {
                    $dt = $event["dtend"];
                    break;
                }
            case "DTSTART":
            default:
                $dt = $event["dtstart"];
                break;
            }
            $evDTstamp[$event["uid"].$dt] = $dt;
        }
        
        array_multisort($evDTstamp, $sortOrder, $events);
    }
    
}
?>
