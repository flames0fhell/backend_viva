<?php

namespace App\Http\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Cache;
use Carbon\Carbon;
/**
 * Class Untuk Model Dipakai secara umum
 * by HitoriAF
 */
class GeneralModel extends Model
{

  function __construct()
  {

  }
  public function getHeadline($params)
  {
    $d['channel_ids'] = isset($params['channel_ids'])?$params['channel_ids']:null;
    $d['exclude_article_ids'] = isset($params['exclude_article_ids'])?$params['exclude_article_ids']:null; //musti array
    $d['level'] = isset($params['level'])?$params['level']:0;
    $d['start'] = isset($params['start'])?$params['start']:0;
    $d['limit'] = isset($params['limit'])?$params['limit']:12;
    $d['show_sql'] = isset($params['show_sql'])?$params['show_sql']:false;
    $d['exclude_channel_id'] = isset($params['exclude_channel_id'])?$params['exclude_channel_id']:array();
    $d['cache_expired'] = isset($params['cache_expired'])?$params['cache_expired']:15; //in minutes
    $d['excludes'] = config('generic.exclude_ids.headline');
    $clause['channel'] = "";
    $profiler = array();
    /*Filter Channel ID*/
    if(!is_null($d['channel_ids']) && $d['level'] > 0){
      if(is_array($d['channel_ids'])){
        //jika channel id berbentuk Array
        $clause['channel'] = " AND channel_level_".$d['level']."_id in (".implode(",",$d['channel_ids']).")";
      }elseif(preg_match("/,/",$d['channel_ids'])){
        //jika channel id banyak pake koma (,)
        $clause['channel'] = " AND channel_level_".$d['level']."_id in (".$d['channel_ids'].")";
      }else{
        //jika channel hanya satu
        $clause['channel'] = " AND channel_level_".$d['level']."_id = ".$d['channel_ids'];
      }
    }
    $clause['exclude_channel'] = "";
    foreach ($d['excludes'] as $k => $ex) {
      if(is_array($ex['channel_id'])){
        //jika channel id berbentuk Array
        $clause['exclude_channel'] .= " AND channel_level_".$ex['level']."_id Not in (".implode(",",$ex['channel_id']).")";
      }elseif(preg_match("/,/",$ex['channel_id'])){
        //jika channel id banyak pake koma (,)
        $clause['exclude_channel'] .= " AND channel_level_".$ex['level']."_id Not in (".$ex['channel_id'].")";
      }else{
        //jika channel hanya satu
        $clause['exclude_channel'] .= " AND channel_level_".$ex['level']."_id != ".$ex['channel_id'];
      }
    }
    if(is_array($d['exclude_article_ids'])){
      $clause['exclude_id'] = " AND id Not In (".implode(",", $d['exclude_article_ids']).")";
    }
    if(is_array($d['exclude_channel_id'])){
      if(count($d['exclude_channel_id']) >0){
        $clause['manual_exclude_id'] = " AND channel_level_1_id Not IN (".implode(",",$d['exclude_channel_id']).")";

      }
    }
    //$clause['date_filter'] = " AND DATE(date_publish) <= '".date("Y-m-d")."'";
    /*End Filter Channel ID*/

    /*Declare Clauses*/
    $clauses = "";
    foreach($clause as $key => $where){
      $clauses .= $where;
    }

    /*Queries*/
      $_sql = "
        Select
          *
        from
          articles
        where
          1=1
          AND is_headline_level_".$d['level']." = 1
          ".$clauses."
        Order By
          date_publish
        Desc
        Limit ".$d['start'].",".$d['limit']."
      ";

      if($d['show_sql']){
        $profiler[] = $_sql;
      }
      $_sql_stickies = "
        Select
          *
        from
          articles
        where
          1=1
          AND is_headline_level_".$d['level']." = 1
          ".$clauses."
          AND is_sticky_headline = 1
          AND DATE(date_publish) = '".date("Y-m-d")."'
        Order By
          date_publish
        Desc
        Limit 0,5
      ";
      if($d['show_sql']){
        $profiler[] = $_sql_stickies;
      }
    /*End Queries*/
    $unique_cache = "getHeadlines:".md5(serialize($d));
    if($d['cache_expired'] > 0 && Cache::has($unique_cache)){
      //jika di cache
      $result = Cache::get($unique_cache);
      return [
        'articles' => $result,
        'profiler' => $profiler
      ];
    }
    $datas = DB::select($_sql);
    $datas = json_decode(json_encode($datas),true);
    $stickies = DB::select($_sql_stickies);
    $stickies = json_decode(json_encode($stickies),true);

    $urutan = 0;
    $stick_no = 0;
    /*plot sticky headline 3,5,7,9
     */
    $plots = [2,4,6,8];//urutan + 1
    $sticky_ids = array();
    foreach ($stickies as $k => $data_sticky) {
      $sticky_ids[$k] = $data_sticky['id'];
    }
    foreach ($datas as $key => $data) {
      $pos = $urutan - 1;
      /*Jika data ada dalam list sticky, lompati*/
        if(in_array($data['id'], $sticky_ids)){
          //data ini ada dalam list sticky, lompati
          continue;
        }
      /*Jika ini adalah plot sticky, reserve*/
        if(in_array($urutan, $plots)){
          //ini plot untuk sticky
          if(isset($stickies[$stick_no])){
            $result[$urutan] = $stickies[$stick_no];
            $images = $this->getArticlePhoto(['article_id' => $stickies[$stick_no]['id']]);
            $result[$urutan]['path'] = isset($images[0]['path'])?$images[0]['path']:'';
            $result[$urutan]['caption'] = isset($images[0]['caption'])?$images[0]['caption']:'';
            $result[$urutan]['orientation'] = isset($images[0]['orientation'])?$images[0]['orientation']:'';
            $stick_no ++;
            $urutan++;
          }
        }
      $result[$urutan] = $data;
      $images = $this->getArticlePhoto(['article_id' => $data['id']]);
      $result[$urutan]['path'] = isset($images[0]['path'])?$images[0]['path']:'';
      $result[$urutan]['caption'] = isset($images[0]['caption'])?$images[0]['caption']:'';
      $result[$urutan]['orientation'] = isset($images[0]['orientation'])?$images[0]['orientation']:'';
      $urutan++;
    }
    if($d['cache_expired'] > 0){
      $expiresAt = Carbon::now()->addMinutes($d['cache_expired']);
      Cache::put($unique_cache,$result,$expiresAt);
    }
    return [
      'articles' => $result,
      'profiler' => $profiler,
    ];
  }
  public function getTerbaru($params = array())
  {
    $d['channel_ids'] = isset($params['channel_ids'])?$params['channel_ids']:null;
    $d['exclude_article_ids'] = isset($params['exclude_article_ids'])?$params['exclude_article_ids']:null; //musti array
    $d['level'] = isset($params['level'])?$params['level']:0;
    $d['start'] = isset($params['start'])?$params['start']:0;
    $d['limit'] = isset($params['limit'])?$params['limit']:12;
    $d['show_sql'] = isset($params['show_sql'])?$params['show_sql']:false;
    $d['exclude_channel_id'] = isset($params['exclude_channel_id'])?$params['exclude_channel_id']:array();
    $d['running_news_only'] = isset($params['running_news_only'])?$params['running_news_only']:false;
    $d['cache_expired'] = isset($params['cache_expired'])?$params['cache_expired']:15; //in minutes
    $d['excludes'] = config('generic.exclude_ids.terbaru');
    $clause['channel'] = "";
    /*Filter Channel ID*/
    if(!is_null($d['channel_ids']) && $d['level'] > 0){
      if(is_array($d['channel_ids'])){
        //jika channel id berbentuk Array
        $clause['channel'] = " AND channel_level_".$d['level']."_id in (".implode(",",$d['channel_ids']).")";
        $selected_channel_ids = $d['channel_ids'];
      }elseif(preg_match("/,/",$d['channel_ids'])){
        //jika channel id banyak pake koma (,)
        $clause['channel'] = " AND channel_level_".$d['level']."_id in (".$d['channel_ids'].")";
        $selected_channel_ids = implode(",",$d['channel_ids']);
      }else{
        //jika channel hanya satu
        $clause['channel'] = " AND channel_level_".$d['level']."_id = ".$d['channel_ids'];
        $selected_channel_ids = [$d['channel_ids']];
      }
    }
    $clause['exclude_channel'] = "";
    foreach ($d['excludes'] as $k => $ex) {
      if(isset($selected_channel_ids)){
        if(in_array($ex['channel_id'], $selected_channel_ids)){
          //jika ini merupakan channel ID yang telah ditentukan, maka jangan di exclude
          continue;
        }
      }
      if(is_array($ex['channel_id'])){
        //jika channel id berbentuk Array
        $clause['exclude_channel'] .= " AND channel_level_".$ex['level']."_id Not in (".implode(",",$ex['channel_id']).")";
      }elseif(preg_match("/,/",$ex['channel_id'])){
        //jika channel id banyak pake koma (,)
        $clause['exclude_channel'] .= " AND channel_level_".$ex['level']."_id Not in (".$ex['channel_id'].")";
      }else{
        //jika channel hanya satu
        $clause['exclude_channel'] .= " AND channel_level_".$ex['level']."_id != ".$ex['channel_id'];
      }
    }
    if(is_array($d['exclude_article_ids'])){
      if(count($d['exclude_article_ids']) > 0){
        $clause['exclude_id'] = " AND id Not IN (".implode(",", $d['exclude_article_ids']).")";
      }
    }else{
      if(!empty($d['exclude_article_ids'])){
        $clause['exclude_id'] = " AND id Not IN (".$d['exclude_article_ids'].")";
      }
    }
    if(is_array($d['exclude_channel_id'])){
      if(count($d['exclude_channel_id']) >0){
        $clause['manual_exclude_id'] = " AND channel_level_1_id Not IN (".implode(",",$d['exclude_channel_id']).")";
      }
    }else{
      if(!empty($d['exclude_channel_id'])){
          $clause['manual_exclude_id'] = " AND channel_level_1_id Not IN (".$d['exclude_channel_id'].")";
      }
    }
    if($d['running_news_only']){
      $clause['running_news_only'] = " AND is_running_news_level_".$d['level']." = 1";
    }
    //$clause['date_filter'] = " AND DATE(date_publish) <= '".date("Y-m-d")."'";
    /*End Filter Channel ID*/

    /*Declare Clauses*/
    $clauses = "";
    $profiler = array();
    foreach($clause as $key => $where){
      $clauses .= $where;
    }
    /*Queries*/
      $_sql = "
        Select
          *
        from
          articles
        where
          1=1
          ".$clauses."
        Order By
          date_publish
        Desc
        Limit ".$d['start'].",".$d['limit']."
      ";

      if($d['show_sql']){
        $profiler[] = $_sql;
      }
    /*End Queries*/
    $unique_cache = "getTerbaru:".md5(serialize($d));
    if($d['cache_expired'] > 0 && Cache::has($unique_cache)){
      $result = Cache::get($unique_cache);
      return [
        'articles' => $result,
        'profiler' => $profiler
      ];
    }
    $articles = DB::select($_sql);
    $articles = json_decode(json_encode($articles),true);

    foreach ($articles as $key => $article) {
      $result[$key] = $article;
      $images = $this->getArticlePhoto(['article_id'=>$article['id']]);
      $result[$key]['path'] = isset($images[0]['path'])?$images[0]['path']:'';
      $result[$key]['caption'] = isset($images[0]['caption'])?$images[0]['caption']:'';
      $result[$key]['orientation'] = isset($images[0]['orientation'])?$images[0]['orientation']:'';
    }
    if($d['cache_expired'] > 0){
      $expiresAt = Carbon::now()->addMinutes($d['cache_expired']);
      Cache::put($unique_cache,$result,$expiresAt);
    }
    return [
      'articles' => $result,
      'profiler' => $profiler
    ];
  }
  public function getArticlePhoto($params)
  {

    $article_id = isset($params['article_id'])?$params['article_id']:null;
    $_sql = "
      SELECT
          *
      FROM
          articles_images
      where
          article_id = $article_id;
    ";
    $unique_cache = "getArticlePhoto:".md5(serialize($params));
    $photo_data = Cache::get($unique_cache, function()use($unique_cache,$_sql){
      $photo_data = DB::select($_sql);
      $photo_data = json_decode(json_encode($photo_data),true);
      $expiresAt = Carbon::now()->addMinutes(15);
      Cache::put($unique_cache,$photo_data,$expiresAt);
      return $photo_data;
    });
    return $photo_data;
  }
}
