<?php
/**
 * This PHP-Class should only read a iCal-File (*.ics), parse it and give an 
 * array with its content.
 *
 * PHP Version 5
 *
 * @category Parser
 * @package  Ics-parser
 * @author   Martin Thoma <info@martin-thoma.de>
 * @license  http://www.opensource.org/licenses/mit-license.php  MIT License
 * @version  SVN: r13
 * @link     http://code.google.com/p/ics-parser/
 * @example  $ical = new ICal('MyCal.ics');
 *           print_r( $ical->events() );
 */

error_reporting(E_ALL);

/**
 * This is the iCal-class
 *
 * @category Parser
 * @package  Ics-parser
 * @author   Martin Thoma <info@martin-thoma.de>
 * @license  http://www.opensource.org/licenses/mit-license.php  MIT License
 * @link     http://code.google.com/p/ics-parser/
 *
 * @param {string} $filename The name of the file which should be parsed
 * @constructor
 */
class ICal
{
    /* How many ToDos are in this ical? */
    public  /** @type {int} */ $todo_count = 0;

    /* How many events are in this ical? */
    public  /** @type {int} */ $event_count = 0; 

    /* The parsed calendar */
    public /** @type {Array} */ $cal;

    /* Which keyword has been added to cal at last? */
    private /** @type {string} */ $_lastKeyWord;
    
    /* Reference of keywords that permit multiple values over multiple lines */
    protected static /** @type {array} */ $_MLMV_keys = array(
            'glob' => array( // Always permits MLMV
                'ATTENDEE', 'COMMENT', 'RSTATUS'
            ),
            'some' => array( // Permits MLMV under some Sections
                'ATTACH' => array('VEVENT', 'VTODO', 'VJOURNAL', 'VALARM'),
                'CATEGORIES' => array('VEVENT', 'VTODO', 'VJOURNAL'),
                'CONTACT' => array('VEVENT', 'VTODO', 'VJOURNAL'),
                'EXDATE' => array('VEVENT', 'VTODO', 'VJOURNAL'),
                'RELATED' => array('VEVENT', 'VTODO', 'VJOURNAL'),
                'RESOURCES' => array('VEVENT', 'VTODO'),
                'RDATE' => array('VEVENT', 'VTODO', 'VJOURNAL')
            ),
            'spec' => array( // Permits MLMV under a specific Section
                'VFREEBUSY' => array('FREEBUSY'),
                'VJOURNAL' => array('DESCRIPTION')
            )
        );
    
    /* Reference of keywords that permit multiple values over a single line,
                                                    along with their data type(s) */
    protected static /** @type {array} */ $_SLMV_keys = array(
            "EXDATE",                    /* DATE / DATE-TIME          */
            "RDATE",                     /* DATE / DATE-TIME / PERIOD */
            "FREEBUSY",                  /* DURATION / PERIOD         */
            "CATEGORIES", "RESOURCES"    /* TEXT                      */
            /* -none- */                 /* FLOAT / INTEGER / TIME    */ 
        );

    /** 
     * Creates the iCal-Object
     * 
     * @param {string} $filename The path to the iCal-file
     *
     * @return Object The iCal-Object
     */ 
    public function __construct($filename) 
    {
        if (!$filename) {
            return false;
        }
        
        $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (stristr($lines[0], 'BEGIN:VCALENDAR') === false) {
            return false;
        } else {
            // TODO: Fix multiline-description problem (see http://tools.ietf.org/html/rfc5545#section-3.8.1.5)
            foreach ($lines as $line) {
                $line = trim($line);
                $add  = $this->keyValueFromString($line);
                if ($add === false) {
                    $this->addCalendarComponentWithKeyAndValue($type, false, $line);
                    continue;
                } 

                list($keyword, $value) = $add;

                switch ($line) {
                // http://www.kanzaki.com/docs/ical/vtodo.html
                case "BEGIN:VTODO": 
                    $this->todo_count++;
                    $type = "VTODO"; 
                    break; 

                // http://www.kanzaki.com/docs/ical/vevent.html
                case "BEGIN:VEVENT": 
                    //echo "vevent gematcht";
                    $this->event_count++;
                    $type = "VEVENT"; 
                    break; 

                //all other special strings
                case "BEGIN:VCALENDAR": 
                case "BEGIN:DAYLIGHT": 
                    // http://www.kanzaki.com/docs/ical/vtimezone.html
                case "BEGIN:VTIMEZONE": 
                case "BEGIN:STANDARD": 
                    $type = $value;
                    break; 
                case "END:VTODO": // end special text - goto VCALENDAR key 
                case "END:VEVENT": 
                case "END:VCALENDAR": 
                case "END:DAYLIGHT": 
                case "END:VTIMEZONE": 
                case "END:STANDARD": 
                    $type = "VCALENDAR"; 
                    break; 
                default:
                    $this->addCalendarComponentWithKeyAndValue($type, 
                                                               $keyword, 
                                                               $value);
                    break; 
                } 
            }
            return $this->cal; 
        }
    }

    /** 
     * Add to $this->ical array one value and key.
     * 
     * @param {string} $component This could be VTODO, VEVENT, VCALENDAR, ... 
     * @param {string} $keyword   The keyword, for example DTSTART
     * @param {string} $value     The value, for example 20110105T090000Z
     *
     * @return {None}
     */ 
    public function addCalendarComponentWithKeyAndValue($component, 
                                                        $keyword, 
                                                        $value) 
    {
        switch ($component) {
        case "VEVENT":
            $count = $this->event_count - 1;
            break;
        case "VTODO":
            $count = $this->todo_count - 1;
            break;
        default:
            $count = -1;
        }
        
        if ($keyword == false) { 
            $keyword = $this->last_keyword;
            $extract = $this->cal[$component][$count][$keyword];
            
            if ($this->_MLMV_check($component, $keyword)) {
                $valCount = count($extract) - 1;
                
                if ($this->_SLMV_check($keyword)) {
                    $value = $this->_SLMV_explode($value);
                    $value[0] = array_pop($extract[$valCount]['value']) . $value[0];
                    $value = array_merge($extract[$valCount]['value'], $value);
                } else {
                    $value = $extract[$valCount]['value'] . $value;
                }
                
                if (isset($extract[$valCount]['params'])) {
                    $params = $extract[$valCount]['params'];
                }
            } else {
                $value = $extract['value'] . $value;
                /* There isn't a check for SLMV here because all SLMV
                    keywords are also MLMV keywords under all circumstances
                    they can validly be used */
                if (isset($extract['params'])) {
                    $params = $extract['params'];
                }
            }
        } else {
            $keyword = explode(";", $keyword);
            if (count($keyword) > 1) {
                $params = array();
                for ($k=1; $k<count($keyword); $k++) {
                    list($paraKey, $paraValue) = explode("=", $keyword[$k], 2);
                    $params[$paraKey] = $paraValue;
                }
            } else {
                $params = isset($params) ? $params : "";
            }
            $keyword = $keyword[0];
            $this->last_keyword = $keyword;
            
            if ($this->_SLMV_check($keyword)) {
                $value = $this->_SLMV_explode($value);
            }
        }
        
        $value = array( "value" => $value );
        if (isset($params) && $params != "") { $value["params"] = $params; }
        
        if ($count == -1) {
            $this->cal[$component][$keyword] = $value; 
        } else {
            if ($this->_MLMV_check($component, $keyword)) {
                if (isset($valCount)) {
                    $this->cal[$component][$count][$keyword][$valCount] = $value;
                } else {
                    $this->cal[$component][$count][$keyword][] = $value;
                }
            } else {
                $this->cal[$component][$count][$keyword] = $value;
            }
        }
    }

    /**
     * Get a key-value pair from a string.
     *
     * @param {string} $text which is like "VCALENDAR:Begin" or "LOCATION:"
     *
     * @return {array} array("VCALENDAR", "Begin")
     */
    public function keyValueFromString($text) 
    {
        preg_match("/([^:]+)[:]([\w\W]*)/", $text, $matches);
        if (count($matches) == 0) {
            return false;
        }
        $matches = array_splice($matches, 1, 2);
        return $matches;
    }
    
    /**
     * Returns a multidimensioned array of arrays with all events. Every event
     *   is an associative array and each property is an element within it.
     *
     * @return {array}
     */
    public function getEvents() 
    {
        $array = $this->cal;
        return $array['VEVENT'];
    }
    
    /**
     * Returns true if the current calendar has events or false if it does not
     *
     * @return {boolean}
     */
    public function hasEvents() 
    {
        return ( count($this->events()) > 0 ? true : false );
    }

    /**
     * Returns false when the current calendar has no events in range, else the
     * events.
     * 
     * Note that this function makes use of a UNIX timestamp. This might be a 
     * problem on January the 29th, 2038.
     * See http://en.wikipedia.org/wiki/Unix_time#Representing_the_number
     *
     * @param {boolean} $rangeStart Either true or false
     * @param {boolean} $rangeEnd   Either true or false
     *
     * @return {mixed}
     */
    public function eventsFromRange($rangeStart = false, $rangeEnd = false) 
    {
        $events = $this->sortEventsWithOrder($this->events(), SORT_ASC);

        if (!$events) {
            return false;
        }

        $extendedEvents = array();
        
        if ($rangeStart !== false) {
            $rangeStart = new DateTime();
        }

        if ($rangeEnd !== false or $rangeEnd <= 0) {
            $rangeEnd = new DateTime('2038/01/18');
        } else {
            $rangeEnd = new DateTime($rangeEnd);
        }

        $rangeStart = $rangeStart->format('U');
        $rangeEnd   = $rangeEnd->format('U');

        

        // loop through all events by adding two new elements
        foreach ($events as $anEvent) {
            $timestamp = $this->iCalDateToUnixTimestamp($anEvent['DTSTART']);
            if ($timestamp >= $rangeStart && $timestamp <= $rangeEnd) {
                $extendedEvents[] = $anEvent;
            }
        }

        return $extendedEvents;
    }
    
    /**
     * Sorts an array of events
     * 
     * This is a rough sort that compares date-time strings. For a more
     * accurate sort that uses Unix times, see class.iCalParser.php
     *
     * @param {array} &$events   An array with events.
     * @param {array} $sortKey   Which date-time to sort by (DTSTART, DTEND, DTSTAMP)
     * @param {array} $sortOrder Either SORT_ASC or SORT_DESC
     */
    public function sortEvents (&$events, $sortKey = "DTSTART", $sortOrder = SORT_ASC) {
        
        if ($sortOrder !== SORT_ASC && $sortOrder !== SORT_DESC) {
            // todo: set error
            return;
        }
        
        $evDTstamp = array();
        foreach ($events as $event) {
            switch ($sortKey) {
            case "DTSTAMP":
                $dt = $event["DTSTAMP"]["value"];
                break;
            case "DTEND":
                if (isset($event["DTEND"])) {
                    $dt = $event["DTEND"]["value"];
                    break;
                }
            case "DTSTART":
            default:
                $dt = $event["DTSTART"]["value"];
                break;
            }
            $evDTstamp[$event["UID"]["value"]] = $dt;
        }
        
        array_multisort($evDTstamp, $sortOrder, $events);
    }
    
    /**
     * Checks whether or not a keyword permit multiple values over multiple lines
     *
     * @param {string} $section The Section the Keyword is contained within
     * @param {string} $keyword The Keyword being looked into
     *
     * @return {boolean}
     */
    private function _MLMV_check ($section, $keyword)
    {
        // convert to uppercase
        $section = strtoupper($section);
        $keyword = strtoupper($keyword);
        
        // Special cases
        if ($section == "VALARM") {
            return ($keyword == "ATTACH");
        } else if ($section == "VTIMEZONE") {
            return false;
        }
        
        // check through array
        $mlmv_glob = in_array($keyword, $this::$_MLMV_keys['glob']);
        $mlmv_some = isset($this::$_MLMV_keys['some'][$keyword]) && in_array($section, $this::$_MLMV_keys['some'][$keyword]);
        $mlmv_spec = isset($this::$_MLMV_keys['spec'][$section]) && in_array($keyword, $this::$_MLMV_keys['spec'][$section]);
        if ($mlmv_glob || $mlmv_some || $mlmv_spec || substr($keyword, 0, 2) == "X-")
        {
            return true;
        }
        return false;
    }
    
    /**
     * Checks whether or not a keyword permits multiple values over multiple lines
     * 
     * @param {string} $keyword The keyword being checked
     * 
     * @return {boolean}
     */
    private function _SLMV_check ($keyword)
    {
        return in_array($keyword, $this::$_SLMV_keys);
    }
    
    /**
     * Explodes apart values from a single string, taking into account the
     *   possibility of an escaped comma.
     * 
     * @params {string} $values The string containing the values separated by commas
     * 
     * @return {array}
     */
    private function _SLMV_explode ($values)
    {
        $exploded = explode(",", $values);
        
        $values = array();
        
        for ($v=0; $v<count($exploded); $v++) {
            $newValue = $exploded[$v];
            while (substr($newValue, -1) == "\\") {
                $v++;
                $newValue .= "," . $exploded[$v];
            }
            $values[] = trim($newValue);
        }
        return $values; 
    }
    
} 
?>
