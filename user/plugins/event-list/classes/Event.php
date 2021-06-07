<?php

namespace Grav\Plugin\EventList;

class Event
{
    /** @var \DateTime */
    public $start_time;

    /** @var \DateTime */
    public $end_time;

    /** @var string */
    public $summary;

    /** @var string */
    public $description;

    /**
     * Constructor
     *
     * @param \DateTime $start_time
     * 
     * @param \DateTime $end_time
     * 
     * @param string $summary
     * 
     * @param description $summary
     */
    public function __construct($start_time, $end_time, $summary, $description)
    {
        $this->start_time = $start_time;
        $this->end_time = $end_time;
        $this->summary = $summary;
        $this->description = $description;
    }

    public function get_first_link()
    {
        // We expect DESCRIPTIOn to be an HTML source with some links. It is
        // then easy to match with urls in `href` properties
        $reg_exUrl = '/"(https?[^"]*)"/m';

        // Check if there is a url in the description
        if (preg_match($reg_exUrl, $this->description, $url)) {
            return $url[1];
        }

        return null;
    }

    /**
     * Compare two events
     *
     * @param Event $event_1
     * 
     * @param Event $event_2
     * 
     * @return bool
     */
    public static function compare($event_1, $event_2)
    {
        // @TODO Maybe we should just compare the date, not the time
        if ($event_1->start_time < $event_2->start_time)
            return -1;
        else if ($event_1->start_time > $event_2->start_time)
            return +1;

        if ($event_1->end_time < $event_2->end_time)
            return -1;
        else if ($event_1->end_time > $event_2->end_time)
            return +1;
        else return 0;
    }
}
