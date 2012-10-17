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
        $db_resp = mysql_fetch_array($r);
        mysql_close($link);
        if($field_to_return!=null){
            return $db_resp[$field_to_return];
        }
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

                $val_str .=  "'" . $val  . "', ";
            }
            $str .= "insert into " . "`" . $table . "` " ;
            $sql_req=$str . "(" . $key_str  . ") values (" . $val_str . "); ";
            $sql_req = str_replace(", )",")",$sql_req);
            var_dump($sql_req);
           return $sql_req;
    }
    
    
    
    /**
     * Insert each comment to database with parents
     */
    
    function insert_comm($comment){

        $status;
        if ($comment['status'] == "APPROVED"){
            $status = 1;
        }
        elseif ($comment['status'] == "PENDING" || $comment['status'] == "REJECTED" ){
            $status = 0;
        }
        elseif ($comment['status'] == "SPAM" ){
            $status = "spam";
        }
        else {
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
    
}

$a = new Cackle();