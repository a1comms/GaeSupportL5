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
        if (!empty($start_time)) {
            self::startSpan('PHP_Fuel_Start', [], $start_time);
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
        foreach(self::$unfinished_spans as $k => $v) {
            self::endSpan($k);
        }

        if (self::$force_untraced) {
            return;
        }

        // Check if we've got a Trace context header.
        if (!empty($_SERVER['HTTP_X_CLOUD_TRACE_CONTEXT'])) {
            // Check if this is a Trace sample request that we want to log data against.
            $e = explode(";", $_SERVER['HTTP_X_CLOUD_TRACE_CONTEXT']);
            if (@$e[1] == 'o=1') {
                $t = explode("/", $e[0]);
                syslog(LOG_NOTICE, 'Current request is a trace sample, saving trace with additonal custom spans: ' . count(self::$spans));
                $projectId = substr($_SERVER['APPLICATION_ID'], (strpos($_SERVER['APPLICATION_ID'], "~")+1));
                $i = 0;
                while ($i < count(self::$spans)) {
                    $trace = [
                        "projectId"     =>  $projectId,
                        "traceId"       =>  $t[0],
                        "parentSpanId"  =>  $t[1],
                        "spans"         =>  array_values(array_slice(self::$spans, $i, 150)),
                    ];
                    if (class_exists('google\appengine\api\taskqueue\PushTask')) {
                        $task1 = new \google\appengine\api\taskqueue\PushTask('/system/traceSubmit', ['data' => json_encode($trace)], ['delay_seconds' => 0, 'method' => 'POST']);
                        $queue = new \google\appengine\api\taskqueue\PushQueue('trace');
                        $queue->addTasks([$task1]);
                    }
                    $i += 150;
                }
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
    public static function startSpan($name, $labels = [], $time = NULL) {
        if (empty($name)) {
            return false;
        }

        $id = self::getUniqueID();
        self::$unfinished_spans[$id] = true;
        self::$spans[$id] = [
            "spanId"    =>  $id,
            "name"      =>  $name,
            "kind"      =>  "SPAN_KIND_UNSPECIFIED",
            "labels"    =>  array_merge( $labels, [ 'START_memory_get_usage' => self::size_convert(memory_get_usage()), 'START_memory_get_peak_usage' => self::size_convert(memory_get_peak_usage()) ] ),
            "startTime" =>  self::getTimeStamp($time),
        ];

        return $id;
    }

    /**
     * Ends an existing event span.
     *
     * @return void
     */
    public static function endSpan($id, $labels = [])
    {
        if (empty(self::$spans[$id])) {
            return false;
        }

        self::$spans[$id]["labels"] = array_merge( ['END_memory_get_usage' => self::size_convert(memory_get_usage()), 'END_memory_get_peak_usage' => self::size_convert(memory_get_peak_usage())], $labels, self::$spans[$id]["labels"] );
        self::$spans[$id]["endTime"] = self::getTimeStamp();
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
        if (empty($time)) {
            $time = microtime(true);
        }

        // Avoid missing dot on full seconds: (string)42 and (string)42.000000 give '42'
        $time = number_format($time, 6, '.', '');
        return \DateTime::createFromFormat('U.u', $time, new \DateTimeZone("UTC"))->format('Y-m-d\TH:i:s.u\Z');
    }

    public static function size_convert($size)
    {
        $unit=array('B','KB','MB','GB','TB','PB');
        return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
    }
}
