<?php

namespace Shpasser\GaeSupportL5\Session;

use GDS;
use Carbon\Carbon;
use Shpasser\GaeSupportL5\Storage\MemcacheContainer;
use SessionHandlerInterface;

define('SESS_APP_NAME', @$_SERVER['APPLICATION_ID'] . '_' . @$_SERVER['CURRENT_MODULE_ID']);
define('SESS_APP_NAME_SESS', '_' . SESS_APP_NAME . '_sess_');

/**
 * DataStoreSessionHandler
 *
 * @uses     SessionHandlerInterface
 *
 * @category GaeSupportL5
 */
class DataStoreSessionHandler implements SessionHandlerInterface
{
    const SESSION_PREFIX = SESS_APP_NAME_SESS;

    /**
     * $expire
     *
     * @var mixed
     *
     * @access private
     */
    private $expire;

    /**
     * $memcacheContainer
     *
     * @var mixed
     *
     * @access private
     */
    private $memcacheContainer;

    /**
     * $lastaccess
     *
     * @var mixed
     *
     * @access private
     */
    private $lastaccess;

    /**
     * $deleteTime
     *
     * @var mixed
     *
     * @access private
     */
    private $deleteTime;

    /**
     * $obj_schema
     *
     * @var mixed
     *
     * @access private
     */
    private $obj_schema;

    /**
     * $obj_store
     *
     * @var mixed
     *
     * @access private
     */
    private $obj_store;

    /**
     * $orig_data
     *
     * @var mixed
     *
     * @access private
     */
    private $orig_data;

    /**
     * __construct
     *
     * @access public
     *
     * @return mixed Value.
     */
    public function __construct()
    {
        $this->memcacheContainer = new MemcacheContainer();

        // Get session max lifetime to leverage Memcache expire functionality.
        $this->expire = ini_get("session.gc_maxlifetime");
        $this->lastaccess = getTimeStamp();
        $this->deleteTime = Carbon::now()->subDay()->toDateTimeString();

        $obj_gateway_one = new \GDS\Gateway\ProtoBuf(null, null);

        $this->obj_schema = (new GDS\Schema('sessions'))
            ->addString('data', false)
            ->addDateTime('lastaccess');

        $this->obj_store = new GDS\Store($this->obj_schema, $obj_gateway_one);
    }

    /**
     * open - Re-initializes existing session, or creates a new one.
     *
     * @param string $savePath    Save path
     * @param string $sessionName Session name
     *
     * @access public
     *
     * @return bool
     */
    public function open($savePath, $sessionName)
    {
        return true;
    }

    /**
     * close - Closes the current session.
     *
     * @access public
     *
     * @return bool
     */
    public function close()
    {
        return $this->memcacheContainer->close();
    }

    /**
     * read - Reads the session data.
     *
     * @param string $id Session ID.
     *
     * @access public
     *
     * @return string
     */
    public function read($id)
    {

        $id = self::SESSION_PREFIX.$id;

        $mdata = $this->memcacheContainer->get($id);
        if ($mdata !== false){
            $this->orig_data = $mdata;
            return $mdata;
        }

        $obj_sess = $this->obj_store->fetchByName($id);

        if($obj_sess instanceof GDS\Entity) {
            $this->orig_data = $obj_sess->data;

            return $obj_sess->data;
        }

        return "";
    }

    /**
     * write - Writes the session data to the storage
     *
     * @param string $id   Session ID
     * @param string $data Serialized session data to save
     *
     * @access public
     *
     * @return string
     */
    public function write($id, $data)
    {
        $id = self::SESSION_PREFIX.$id;
        $result = $this->memcacheContainer->set($id, $data, $this->expire);

        $obj_sess = $this->obj_store->createEntity([
            'data'          => $data,
            'lastaccess'    => $this->lastaccess
        ])->setKeyName($id);

        if ($this->orig_data != $data){
            $this->obj_store->upsert($obj_sess);
        }

        return $result;
    }

    /**
     * destroy - Destroys a session.
     *
     * @param tring $id Session ID
     *
     * @access public
     *
     * @return bool
     */
    public function destroy($id)
    {
        $id = self::SESSION_PREFIX.$id;

        $result = $this->memcacheContainer->delete($id);

        $obj_sess = $this->obj_store->fetchByName($id);

        if($obj_sess instanceof GDS\Entity) {
            $this->obj_store->delete($obj_sess);
        }

        return $result;
    }

    /**
     * gc - Cleans up expired sessions (garbage collection).
     *
     * @param string|int $maxlifetime Sessions that have not updated for the last maxlifetime seconds will be removed
     *
     * @access public
     *
     * @return bool
     */
    public function gc($maxlifetime)
    {
        return true;
    }

    /**
     * googlegc - Cleans up expired sessions in GAE datastore (garbage collection).
     *
     * @access public
     *
     * @return mixed Value.
     */
    public function googlegc()
    {
        $arr = $this->obj_store->fetchAll("SELECT * FROM sessions WHERE lastaccess < @old", ['old' => $this->deleteTime]);
        syslog(LOG_INFO, 'Found '.count($arr).' records');

        if (!empty($arr)) {
            $this->obj_store->delete($arr);
        }
    }
}
