<?php
namespace App\Http\Controllers;
use Illuminate\Http\Response;
use App\Http\Models\GeneralModel as GeneralModel;

use Illuminate\Support\Facades\Redis as Redis;

use Illuminate\Http\Request;
class WebController extends Controller
{
    var $generalmodel;
    public function __construct()
    {
      $this->generalmodel = new GeneralModel;
    }

    public function headline(Request $request)
    {
      $p = $request->input('data');
      //return $p;
      $headline = $this->generalmodel->getHeadline([
        //'level'    => 1
        //,'channel_ids' => 15
        'start'    => $p['start']
        ,'limit'    => $p['limit']
        ,'show_sql' => true
        ,'exclude_channel_id' => $p['exclude_channel']
        ,'cache_expired' => isset($p['cache_expired'])?$p['cache_expired']:15,
      ]);
      return $headline;
    }
    public function terbaru(Request $request)
    {
      $data = $request->input('data');
      $terbaru = $this->generalmodel->getTerbaru([
          'start'     => $data['start_index'],
          'limit'     => $data['record_count'],
          'level'    => isset($data['level'])?$data['level']:null,
          'channel_ids' => isset($data['channel_id'])?$data['channel_id']:null,
          'exclude_article_ids' => isset($data['exclude_ids'])?$data['exclude_ids']:null,
          'exclude_channel_id' => isset($data['exclude_channel'])?$data['exclude_channel']:null,
          'show_sql'  => true,
          'running_news_only' => isset($data['running_news_only'])?$data['running_news_only']:false,
          'cache_expired' => isset($data['cache_expired'])?$data['cache_expired']:15,
        ]);
      return $terbaru;
    }
}
