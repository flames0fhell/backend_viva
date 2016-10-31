<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$app->get('/', function () use ($app) {
    return 'Restricted Area, you shouldn\'nt be here....';
});
$app->get('tes', function() use($app){
    return "Ini tes";
});



$app->group(['prefix' => 'webservice','namespace' => 'App\Http\Controllers'], function () use($app){
  /*start*/
  $app->get('headline',
              ['as'=>'web_headline',
              'uses' => 'WebController@headline']);

  /*End*/
  /*start*/
  $app->get('terbaru',
              ['as'=>'web_terbaru',
              'uses' => 'WebController@terbaru']);

  /*End*/
});
