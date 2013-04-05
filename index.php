<?php
//Define db connection settings
define('CACKLE_DB_LOCALHOST', "localhost");
define('CACKLE_DB_USER',  "root");
define('CACKLE_DB_PASSWORD', "");
define('CACKLE_DB_NAME', "cackle-php" );
//Define timer
define('CACKLE_TIMER', 60);
//Define Cackle API
define('ACCOUNT_API_KEY',  "");
define('SITE_API_KEY', "");



class Cackle{
    function Cackle(){
        if ($this->time_is_over(CACKLE_TIMER)){
            $this->comment_sync(ACCOUNT_API_KEY,SITE_API_KEY);      
        }
        $this->cackle_display_comments();
    }
    
    function time_is_over($cron_time){
        $sql="select common_value from common where `common_name` = 'last_time'";
        $get_last_time = $this->db_connect($sql, "common_value");
        $now=time();
        $establish_time_sql="insert into `common` (`common_name`,`common_value`) values ('last_time',$now)";
        $delete_time_sql="delete from `common` where `common_name` = 'last_time' and `common_value` > 0;";
        if ($get_last_time==null){
            $this->db_connect($establish_time_sql);
            return time();
        }
        else{
            if($get_last_time + $cron_time > $now){
                return false;
            }
            if($get_last_time + $cron_time < $now){
                $this->db_connect($delete_time_sql);
                $this->db_connect($establish_time_sql);
                return $cron_time;
            }
        }
    }
    function db_connect($sql,$field_to_return=null){
        $link = mysql_connect(CACKLE_DB_LOCALHOST, CACKLE_DB_USER, CACKLE_DB_PASSWORD) or die("Could not connect\n");
        mysql_select_db(CACKLE_DB_NAME, $link) or die(mysql_error());
        mysql_query('SET NAMES \'UTF8\'');
        $r = mysql_query($sql, $link) or die(mysql_error());
        
        
        if($field_to_return!=null){
            $db_resp = mysql_fetch_array($r);
            return $db_resp[$field_to_return];
        }
        $x=0;
        if($r==1) return;  // no select request
	$row=array();      // to be safe with empty select results
        while ($res=mysql_fetch_array($r)) {
            
            $row[$x]=$res;
            $x++;
        }
        return $row;
        mysql_close($link);
    }

    function comment_sync($accountApiKey,$siteApiKey,$cackle_last_comment=0){
        $get_last_comment = $this->db_connect("select common_value from common where `common_name` = 'last_comment'","common_value");
        if ($get_last_comment!=null){
            $cackle_last_comment = $get_last_comment;
        }
        $params1 = "accountApiKey=$accountApiKey&siteApiKey=$siteApiKey&id=$cackle_last_comment";
        $host="cackle.me/api/comment/list?$params1";
        
        
        function curl($url)
        {
            $ch = curl_init();
            curl_setopt ($ch, CURLOPT_URL,$url);
            curl_setopt ($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6");
            curl_setopt ($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
            $result = curl_exec ($ch);
            curl_close($ch);
            
            return $result;
        }
        $response = curl($host);
        $response = $this->cackle_json_decodes($response);
        $this->push_comments($response);
      
    }
    function cackle_json_decodes($response){
        $response_without_jquery = str_replace('jQuery(', '', $response);
        $response = str_replace(');', '', $response_without_jquery);
        $obj = json_decode($response,true);
    
        return $obj;
    }
    
    function insCreate ($table,$arr){
            $str="";
            $key_str="";
            $val_str="";
            
            foreach($arr as $key=>$val){
                
                $key_str .= "`" . $key  . "`, ";

                $val_str .=  "'" . mysql_escape_string($val)  . "', ";
            }
            $str .= "insert into " . "`" . $table . "` " ;
            $sql_req=$str . "(" . $key_str  . ") values (" . $val_str . "); ";
            $sql_req = str_replace(", )",")",$sql_req);
           return $sql_req;
    }
    
    
    
    /**
     * Insert each comment to database with parents
     */
    
    function insert_comm($comment){
        $status;
        if (strtolower($comment['status']) == "approved") {
          $status = 1;
        }
        elseif (strtolower($comment['status'] == "pending") || strtolower($comment['status']) == "rejected") {
          $status = 0;
        }
        elseif (strtolower($comment['status']) == "spam") {
          $status = "spam";
        }
        elseif (strtolower($comment['status']) == "deleted") {
        $status = "trash";
    }
    
        /*
         * Here you can convert $url to your post ID
         */
        
        $url = $comment['channel'];
        
        if ($comment['author']!=null){
            $author_name = $comment['author']['name'];
            $author_email=  $comment['author']['email'];
            $author_www = $comment['author']['www'];
            $author_avatar = $comment['author']['avatar'];
            $author_provider = $comment['author']['provider'];
            $author_anonym_name = null;
            $anonym_email = null;
        }
        else{
            $author_name = null;
            $author_email= null;
            $author_www = null;
            $author_avatar = null;
            $author_provider = null;
            $author_anonym_name = $comment['anonym']['name'];
            $anonym_email = $comment['anonym']['email'];

        }
        $get_parent_local_id = null;
        $comment_id = $comment['id']; 
        if ($comment['parentId']) {
            $comment_parent_id = $comment['parentId'];
            $sql = "select comment_id from comment where user_agent='Cackle:$comment_parent_id';";
            $get_parent_local_id = $this->db_connect($sql, "comment_id"); //get parent comment_id in local db
        }
        //You should define post_id  in $commentdata according you cms engine(ex. maybe your cms have function to return post_id by page's url) 
        $commentdata = array(
                'url' => $url,
                'author_name' =>  $author_name,
                'author_email' =>  $author_email,
                'author_www' =>  $author_www,
                'author_avatar' =>  $author_avatar,
                'author_provider' =>  $author_provider,
                'anonym_name' =>  $author_anonym_name,
                'anonym_email' =>  $anonym_email,
                'rating' => $comment['rating'],
                'created' => strftime("%Y-%m-%d %H:%M:%S", $comment['created']/1000),
                'ip' => $comment['ip'],
                'message' =>$comment['message'],
                'status' => $status,
                'user_agent' => 'Cackle:' . $comment['id'],
                'parent' => $get_parent_local_id
                
        );
        $this->db_connect($this->insCreate("comment",$commentdata));
        $sql_last_comment_delete="delete from `common` where `common_name` = 'last_comment'";
        $sql_last_comment_establish="insert into `common` set `common_name` = 'last_comment'";
        $sql_last_comment="update `common` set `common_name` = 'last_comment', `common_value` = $comment_id where `common_name` = 'last_comment';";
        $this->db_connect($sql_last_comment_delete);
        $this->db_connect($sql_last_comment_establish);
        $this->db_connect($sql_last_comment);
        
    }
   
    function push_comments ($response){
        $obj = $response['comments'];
        foreach ($obj as $comment) {
            $this->insert_comm($comment);
        }
    }
     function cackle_comment( $comment) {
        
        ?><li  id="cackle-comment-<?php echo $comment['comment_id']; ?>">
              <div id="cackle-comment-header-<?php echo $comment['comment_id']; ?>" class="cackle-comment-header">
                  <cite id="cackle-cite-<?php echo $comment['comment_id']; ?>">
                  <?php if($comment['author_name']) : ?>
                      <a id="cackle-author-user-<?php echo $comment['comment_id']; ?>" href="<?php echo $comment['author_www']; ?>" target="_blank" rel="nofollow"><?php echo $comment['author_name']; ?></a>
                  <?php else : ?>
                      <span id="cackle-author-user-<?php echo $comment['comment_id']; ?>"><?php echo $comment['anonym_name']; ?></span>
                  <?php endif; ?>
                  </cite>
              </div>
              <div id="cackle-comment-body-<?php echo $comment['comment_id']; ?>" class="cackle-comment-body">
                  <div id="cackle-comment-message-<?php echo $comment['comment_id']; ?>" class="cackle-comment-message">
                  <?php echo $comment['message']; ?>
                  </div>
              </div>
          </li><?php } 
    
     
     function cackle_display_comments(){ ?>
         <div id="mc-container">
            <div id="mc-content">
                <ul id="cackle-comments">
                <?php $this->list_comments(); ?> 
                </ul>
            </div>
        </div>
        <script type="text/javascript">
        var mcSite = '<?php echo $api_id?>';
        var mcChannel = '<?php echo $post->ID?>';
        document.getElementById('mc-container').innerHTML = '';
        (function() {
            var mc = document.createElement('script');
            mc.type = 'text/javascript';
            mc.async = true;
            mc.src = 'http://cackle.me/mc.widget-min.js';
            (document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(mc);
        })();
        </script>
<?php }
    function get_local_comments(){
        //getting all comments for special post_id from database. 
        //$post_id = 1;
        $get_all_comments = $this->db_connect("select * from `comment` where `post_id` = $post_id and `status` = 1;");
        return $get_all_comments;
    }
    function list_comments(){
        $obj = $this->get_local_comments();
        foreach ($obj as $comment) {
            $this->cackle_comment($comment);
        }
    }
}
$a = new Cackle();
?>