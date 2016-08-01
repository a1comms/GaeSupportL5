<?php

namespace Shpasser\GaeSupportL5\Trace;

/**
 * Tracing Class
 */
class GAETrace
{
    /**
     * Stores event spans for submission.
     *
     * @var array
     */
    protected static $spans = [];

    /**
     * Stores unfinished span IDs.
     *
     * @var array
     */
    protected static $unfinished_spans = [];

    /**
     * If true, this execution is forced as untraced.
     *
     * @var array
     */
    protected static $force_untraced = false;

    /**
     * Setup the trace environment on startup.
     *
     * @return void
     */
    public function __construct($start_time = NULL)
    {
        if (!empty($start_time))
        {
            self::startSpan('PHP_Laravel_Start', $start_time);
        }
    }

    /**
     * Submit the trace data at the end of a request.
     *
     * @return void
     */
    public function __destruct()
    {
        // End any spans that haven't been finished,
        // probably due to a premature exit.
        foreach(self::$unfinished_spans as $k => $v)
        {
            self::endSpan($k);
        }

        if (self::$force_untraced)
        {
            return;
        }

        // Check if we've got a Trace context header.
        if (!empty($_SERVER['HTTP_X_CLOUD_TRACE_CONTEXT']))
        {
            // Check if this is a Trace sample request that we want to log data against.
            $e = explode(";", $_SERVER['HTTP_X_CLOUD_TRACE_CONTEXT']);
            if (@$e[1] == 'o=1')
            {
                $t = explode("/", $e[0]);
                syslog(LOG_NOTICE, 'Current request is a trace sample, saving trace with additonal custom spans: ' . count(self::$spans));

                $client = new \Google_Client();
                $client->useApplicationDefaultCredentials();
                $client->addScope('https://www.googleapis.com/auth/cloud-platform');

                $projectId = substr($_SERVER['APPLICATION_ID'], (strpos($_SERVER['APPLICATION_ID'], "~")+1));

                $trace = new \Google_Service_CloudTrace_Trace();
                $trace->setProjectId($projectId);
                $trace->setTraceId($t[0]);
                $trace->setSpans(array_values(self::$spans));
                $postBody = new \Google_Service_CloudTrace_Traces($client);
                $postBody->setTraces([$trace]);
                if (class_exists('google\appengine\api\taskqueue\PushTask'))
                {
                    $task1 = new \google\appengine\api\taskqueue\PushTask('/gae/trace_submit', ['data' => serialize($postBody)], ['delay_seconds' => 0, 'method' => 'POST']);
                    $queue = new \google\appengine\api\taskqueue\PushQueue('trace');
                    $queue->addTasks([$task1]);
                }
            }
        } else {
            syslog(LOG_INFO, 'No Trace Header');
        }
    }

    public static function submitTraceAsync(){
        self::$force_untraced = true;

        if (!empty($_POST['data']))
        {
            $client = new \Google_Client();
            $client->useApplicationDefaultCredentials();
            $client->addScope('https://www.googleapis.com/auth/cloud-platform');

            $service = new \Google_Service_CloudTrace($client);

            $projectId = substr($_SERVER['APPLICATION_ID'], (strpos($_SERVER['APPLICATION_ID'], "~")+1));

            $data = unserialize($_POST['data']);
            if ($data instanceof \Google_Service_CloudTrace_Traces)
            {
                $response = $service->projects->patchTraces($projectId, $data);
            }
        }
    }

    /**
     * Start an event span to measure time.
     * Returns a SpanID for endSpan.
     *
     * @return string
     */
    public static function startSpan($name, $time = NULL)
    {
        if (empty($name))
        {
            return false;
        }
        $id = self::getUniqueID();

        self::$unfinished_spans[$id] = true;

        self::$spans[$id] = new \Google_Service_CloudTrace_TraceSpan();
        self::$spans[$id]->setSpanId($id);
        self::$spans[$id]->setName($name);
        self::$spans[$id]->setKind("SPAN_KIND_UNSPECIFIED");
        self::$spans[$id]->setStartTime(self::getTimeStamp($time));

        return $id;
    }

    /**
     * Ends an existing event span.
     *
     * @return void
     */
    public static function endSpan($id)
    {
        if (empty(self::$spans[$id]))
        {
            return false;
        }

        self::$spans[$id]->setEndTime(self::getTimeStamp());
        unset(self::$unfinished_spans[$id]);
    }

    /**
     * Generated a Unique ID for a span.
     *
     * @return string
     */
    public static function getUniqueID()
    {
        return str_replace(".", "", microtime(true));
    }

    /**
     * Generates an RFC3339 UTC "Zulu" format timestamp with microsecond precision.
     *
     * @return string
     */
    public static function getTimeStamp($time = NULL)
    {
        if (empty($time))
        {
            $time = microtime(true);
        }

        // Avoid missing dot on full seconds: (string)42 and (string)42.000000 give '42'
        $time = number_format($time, 6, '.', '');

        return \DateTime::createFromFormat('U.u', $time, new \DateTimeZone("UTC"))->format('Y-m-d\TH:i:s.u\Z');
    }
}
