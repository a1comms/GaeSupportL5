<?php

use \Shpasser\GaeSupportL5\Http\Controllers\ArtisanConsoleController;
use \Shpasser\GaeSupportL5\Http\Controllers\SessionGarbageCollectionController;

/**
 * Maintenance routes.
 */
Route::get('gae/artisan',  array('as' => 'artisan',
    'uses' => ArtisanConsoleController::class.'@show'));

Route::post('gae/artisan', array('as' => 'artisan',
    'uses' => ArtisanConsoleController::class.'@execute'));

Route::get('gae/sessiongc',  array('as' => 'sessiongc',
    'uses' => SessionGarbageCollectionController::class.'@run'));
