<?php

namespace Shpasser\GaeSupportL5\Storage;

use Illuminate\Filesystem\Filesystem as Filesystem;

/**
* MemcacheContainer
*
* @uses     Filesystem
*
* @package  app
*/
final class MemcacheContainer extends Filesystem {

    /**
    * $memcache - The memcache object for storing sessions.
    *
    * @var mixed
    *
    * @access private
    */
    private $memcache = null;

    /**
    * __construct - Initialises a Memcache instance
    * 
    * @access public
    *
    * @return void
    */
    public function __construct() {
        $this->memcache = new \Memcache();
        $this->memcache->addServer(config('view.memcached_host'), config('view.memcached_port'));
    }

    /**
    * close - Closes the Memcache instance.
    * 
    * @access public
    *
    * @return bool
    */
    public function close() {
        return $this->memcache->close();
    }

    /**
     * get - Finds the value associated with input key, from Memcache.
     * 
     * @param string $key Input key from which to find value
     *
     * @access public
     *
     * @return string
     */
    public function get($key) {
        return $this->memcache->get($key, null);
    }

    /**
     * set - Inserts a key value pair, with expiry time, into Memcache.
     * 
     * @param string $key   Input key to associate with the value
     * @param string $value Input value to be stored
     * @param int $expire   Time until the pair can be garbage collected
     *
     * @access public
     *
     * @return bool
     */
    public function set($key, $value, $expire) {
        return $this->memcache->set($key, $value, null, $expire);
    }

    /**
     * delete - Removes the key value pair, keyed with the input variable.
     * 
     * @param string $key Input key to remove key value pair
     *
     * @access public
     *
     * @return bool
     */
    public function delete($key) {
        return $this->memcache->delete($key);
    }
}