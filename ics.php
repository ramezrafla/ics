<?php
    // Require composer autoload for direct installs
    @include __DIR__ . '/vendor/autoload.php';

    /**
     * Banner ICS
     *
     * Displays event information from ICS attachments
     *
     * @license MIT License: <http://opensource.org/licenses/MIT>
     * @author Varun Patil
     * @category  Plugin for RoundCube WebMail
     */
    class ics extends rcube_plugin
    {
        public $task = 'mail';
        private $rc;
        private $logPath;
        private $debug;

        function init()
        {
            $this->rc = rcube::get_instance();
            $this->load_config('config.inc.php');

            $this->include_stylesheet('ics.css');
            $this->include_script('ics.js');
            $this->add_hook('message_objects', array($this, 'ics_banner'));
            $this->logPath = __DIR__  .  '/../../logs/ics.log';
            $this->debug = $this->rc->config->get('debug');
            $this->log('ics loaded');
            $this->register_action('plugin.ics', array($this, 'actions'));
        }

        private function log($string) {
          if ($this->debug) {
            $log = date('Y-m-d H:i:s') . ' ' . $string;
            file_put_contents($this->logPath, $log . PHP_EOL, FILE_APPEND);
          }
        }

        public function ics_banner($args)
        {
            // Get arguments
            $content = $args['content'];
            $message = $args['message'];

            foreach ($message->attachments as &$a) {
                if (strtolower($a->mimetype) === 'text/calendar') {
                    try {
                        $this->process_attachment($content, $message, $a);
                    } catch (\Exception $e) {}
                }
            }

            return array('content' => $content);
        }

        public function actions() 
        {

        }

        public function process_attachment(&$content, &$message, &$a)
        {
            $rcmail = rcmail::get_instance();
            $date_format = $rcmail->config->get('date_format', 'D M d, Y');
            $time_format = $rcmail->config->get('time_format', 'h:ia');
            $combined_format = $date_format . ' ' . $time_format;

            // Parse event
            $ics = $message->get_part_body($a->mime_id);
            $ical = new \ICal\ICal();
            $ical->initString($ics);

            // Make sure we have events
            if (!$ical->hasEvents()) return;

            // Get first event
            foreach ($ical->events() as &$event) {
                $dtstart = $event->dtstart_array[2];
                $dtend = $event->dtend_array[2];
                $dtstr = $rcmail->format_date($dtstart, $combined_format) . ' - ';

                // Dont double date if same
                $df = 'Y-m-d';
                if (date($df, $dtstart) === date($df, $dtend)) {
                    $dtstr .= $rcmail->format_date($dtend, $time_format);
                } else {
                    $dtstr .= $rcmail->format_date($dtend, $combined_format);
                }

                // Put timezone in date string
                $dtstr .= ' (' . $rcmail->format_date($dtstart, 'T') . ')';

                // Get attendees
                $who = array();
                foreach (array_merge($event->organizer_array ?? [], $event->attendee_array ?? []) as &$o) {
                    if (is_array($o) && array_key_exists('CN', $o) && !in_array($o['CN'], $who)) {
                        array_push($who, $o['CN']);
                    }
                }

                if (count($who) > 0) {
                  $max_show = 20;
                  $others = count($who) - $max_show;
                  $who = array_slice($who, 0, $max_show);
                  $who = implode(', ', $who) . ($others > 0 ? " and $others others" : '');
                } else {
                    $who = null;
                }

                // Output
                $html = '<div class="info ics-event-container">';
                $html .= '<div class="ics-icon">';
                $html .= '<div class="m">' . $rcmail->format_date($dtstart, 'M') . '</div>';
                $html .= '<div class="d">' . $rcmail->format_date($dtstart, 'd') . '</div>';
                $html .= '<div class="day">' . $rcmail->format_date($dtstart, 'D') . '</div>';
                $html .= '</div>';
                $html .= '<div class="ics-event">';
                $html .= '<span class="title">' . htmlspecialchars($event->summary) . '</span>';
                $html .= '<br/><b>' . htmlspecialchars($dtstr) . '</b>';
                if (isset($who)) {
                     $html .= '<br/>' . htmlspecialchars($who);
                }
                $html .= '</div>';
                $html .= '</div>';
                array_push($content, $html);
                $this->log($html);

                // description block
                $html = '<div class="info ics-event-description">';
                $description = htmlspecialchars_decode($event->description);
                $html .= (new rcube_text2html($description))->get_html();
                $html .= '</div>';
                array_push($content, $html);
                $this->log($html);

                // buttons
                $html = '<div class="ics-buttons">';
                $html .= '<div class="btn btn-success ics-accept">Accept</div>';
                $html .= '<div class="btn btn-danger ics-decline">Decline</div>';
                $html .= '</div>';
                array_push($content, $html);
            }
        }
    }

