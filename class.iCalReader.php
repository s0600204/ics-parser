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
 * @example  $ical = new ical('MyCal.ics');
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
 * @param {string} filename The name of the file which should be parsed
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
    private static /** @type {array} */ $_MLMV_keys = array(
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
    private static /** @type {array} */ $_SLMV_keys = array(
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
     * Get a key-value pair of a string.
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
     * Return Unix timestamp from ical date time format 
     * 
     * @param {string} $icalDate A Date in the format YYYYMMDD[T]HHMMSS[Z] or
     *                           YYYYMMDD[T]HHMMSS
     *
     * @return {int} 
     */ 
    public function iCalDateToUnixTimestamp($icalDate) 
    { 
        $icalDate = str_replace('T', '', $icalDate); 
        $icalDate = str_replace('Z', '', $icalDate); 

        $pattern  = '/([0-9]{4})';   // 1: YYYY
        $pattern .= '([0-9]{2})';    // 2: MM
        $pattern .= '([0-9]{2})';    // 3: DD
        $pattern .= '([0-9]{0,2})';  // 4: HH
        $pattern .= '([0-9]{0,2})';  // 5: MM
        $pattern .= '([0-9]{0,2})/'; // 6: SS
        preg_match($pattern, $icalDate, $date); 

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
        return  $timestamp;
    } 

    /**
     * Returns an array of arrays with all events. Every event is an associative
     * array and each property is an element it.
     *
     * @return {array}
     */
    public function events() 
    {
        $array = $this->cal;
        return $array['VEVENT'];
    }

    /**
     * Returns a boolean value whether thr current calendar has events or not
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
     * Returns a boolean value whether thr current calendar has events or not
     *
     * @param {array} $events    An array with events.
     * @param {array} $sortOrder Either SORT_ASC, SORT_DESC, SORT_REGULAR, 
     *                           SORT_NUMERIC, SORT_STRING
     *
     * @return {boolean}
     */
    public function sortEventsWithOrder($events, $sortOrder = SORT_ASC)
    {
        $extendedEvents = array();
        
        // loop through all events by adding two new elements
        foreach ($events as $anEvent) {
            if (!array_key_exists('UNIX_TIMESTAMP', $anEvent)) {
                $anEvent['UNIX_TIMESTAMP'] = 
                            $this->iCalDateToUnixTimestamp($anEvent['DTSTART']);
            }

            if (!array_key_exists('REAL_DATETIME', $anEvent)) {
                $anEvent['REAL_DATETIME'] = 
                            date("d.m.Y", $anEvent['UNIX_TIMESTAMP']);
            }
            
            $extendedEvents[] = $anEvent;
        }
        
        foreach ($extendedEvents as $key => $value) {
            $timestamp[$key] = $value['UNIX_TIMESTAMP'];
        }
        array_multisort($timestamp, $sortOrder, $extendedEvents);

        return $extendedEvents;
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
        if (in_array($keyword, $this::$_MLMV_keys['glob'])
                || isset($this::$_MLMV_keys['some'][$keyword]) && in_array($section, $this::$_MLMV_keys['some'][$keyword])
                || isset($this::$_MLMV_keys['spec'][$section]) && in_array($keyword, $this::$_MLMV_keys['spec'][$section])
                || substr($keyword, 0, 2) == "X-")
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
     * Explodes apart values from a single string,
     *               taking into account possibility of an escaped comma
     * 
     * @params {string} $values The srting containing the values separated by commas
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
