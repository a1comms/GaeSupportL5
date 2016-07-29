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
     * Setup the trace environment on startup.
     *
     * @return void
     */
    public function __construct()
    {

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
        foreach(self::$unfinished_spans as $k => $v){
            self::endSpan($k);
        }

        // Check if we've got a Trace context header.
        if (!empty($_SERVER['HTTP_X_CLOUD_TRACE_CONTEXT'])){
            // Check if this is a Trace sample request that we want to log data against.
            $e = explode(";", $_SERVER['HTTP_X_CLOUD_TRACE_CONTEXT']);
            if (@$e[1] == 'o=1'){
                $t = explode("/", $e[0]);
                syslog(LOG_NOTICE, 'Current request is a trace sample, saving trace with additonal custom spans: ' . count(self::$spans));

                $client = new \Google_Client();
                $client->useApplicationDefaultCredentials();
                $client->addScope('https://www.googleapis.com/auth/cloud-platform');

                $service = new \Google_Service_CloudTrace($client);

                $projectId = substr($_SERVER['APPLICATION_ID'], (strpos($_SERVER['APPLICATION_ID'], "~")+1));

                $trace = new \Google_Service_CloudTrace_Trace();
                $trace->setProjectId($projectId);
                $trace->setTraceId($t[0]);
                $trace->setSpans(array_values(self::$spans));
                $postBody = new \Google_Service_CloudTrace_Traces($client);
                $postBody->setTraces([$trace]);
                $response = $service->projects->patchTraces($projectId, $postBody);
            }
        } else {
            syslog(LOG_INFO, 'No Trace Header');
        }
    }

    /**
     * Start an event span to measure time.
     * Returns a SpanID for endSpan.
     *
     * @return string
     */
    public static function startSpan($name)
    {
        if (empty($name)){
            return false;
        }
        $id = self::getUniqueID();

        self::$unfinished_spans[$id] = true;

        self::$spans[$id] = new \Google_Service_CloudTrace_TraceSpan();
        self::$spans[$id]->setSpanId($id);
        self::$spans[$id]->setName($name);
        self::$spans[$id]->setKind("SPAN_KIND_UNSPECIFIED");
        self::$spans[$id]->setStartTime(self::getTimeStamp());

        return $id;
    }

    /**
     * Ends an existing event span.
     *
     * @return void
     */
    public static function endSpan($id)
    {
        if (empty(self::$spans[$id])){
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
    public static function getTimeStamp()
    {
        $time = microtime(true);

        // Avoid missing dot on full seconds: (string)42 and (string)42.000000 give '42'
        $time = number_format($time, 6, '.', '');

        return \DateTime::createFromFormat('U.u', $time, new \DateTimeZone("UTC"))->format('Y-m-d\TH:i:s.u\Z');
    }
}
