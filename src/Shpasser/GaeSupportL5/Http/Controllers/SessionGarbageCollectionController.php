<?php

namespace Shpasser\GaeSupportL5\Http\Controllers;

use Illuminate\Routing\Controller;
use Shpasser\GaeSupportL5\Session\DataStoreSessionHandler;

/**
 * SessionGarbageCollectionController
 *
 * @uses     Controller
 *
 * @category  GaeSupportL5
 */
class SessionGarbageCollectionController extends Controller
{
    /**
     * run
     *
     * @access public
     *
     * @return void
     */
    public function run(){
        $s = new DataStoreSessionHandler();
        $s->googlegc();
    }
}
