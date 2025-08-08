<?php
    class url {
        function __construct() {
            global $conn;
            $this->conn = $conn;
        }
        //精简url
        public function tiny_url($url){
            if(stripos($url,"HTTP://") === 0)
                $url = substr($url,7,strlen($url));
            if(stripos($url,"HTTPS://") === 0)
            {
                $url = "//".substr($url,8,strlen($url));
            }
            if(substr($url, strlen($url) - 1) == '/') $url = substr($url,0,strlen($url) - 1);
            return str_replace("'","%27",str_replace("\\","%5C",$url));
        }
        // 生成短地址
        public function set_url($url, $size = 4) {
            global $config;
            $url = $this->tiny_url($url);
            //切割出#后的数据
            $last = strrpos($url,"#");
            $last_text = "";
            if($last){
                $arr_list = explode("#",$url);
                array_shift($arr_list);
                $last_text = "#" . implode("#", $arr_list);
                $url = substr($url,0,$last);
            }
            $id = $this->get_id($url);
            if(!$id) {
                $id = $this->create_id($url, $size);
                $time = date("Y-m-d H:i:s");
                if(!mysqli_query($this->conn,"INSERT INTO urls(id,data,time)VALUES('$id','$url','$time')")){
                    exit('get url error'.mysqli_error($this->conn));
                }
            }
            $s_url = $id;
            if($config['url'] != "")//自定义前缀
            {
                $hurl = $config['url'];
                if(substr($hurl, strlen($hurl) - 1) != '/') $hurl .= '/';
                $s_url = $hurl . $s_url;
            }
            else
            {
                $s_url = get_uri() . $s_url;
            }
            if($last)
                $s_url = $s_url . $last_text;
            return $s_url;
        }
        // 生成地址 ID
        public function create_id($url, $size = 4) {
            $str = "";
            for($i=0;$i<$size;$i++)
            {
                $str .= mb_chr(rand(0x2E80, 0x9FFF));
            }
            // 重复 ID 检测
            if($this->get_url($str)) {
                return $this->create_id($url, $size+1);
            } else {
                return $str;
            }
            return $str;
        }
        // 查询 ID 号
        public function get_id($url) {
            $check_query = mysqli_query($this->conn,"select id from urls where data='$url' limit 1");
            if(!$check_query){
                return false;
            }
            $result = mysqli_fetch_array($check_query,MYSQLI_ASSOC);
            if(!$result){
                return false;
            }
            else{
                return $result["id"];
            }
        }
        // 查询目标地址
        public function get_url($id,$update = false) {
            $id = str_replace("'","%27",str_replace("\\","%5C",$id));
            $check_query = mysqli_query($this->conn,"select * from urls where id='$id' limit 1");
            if(!$check_query){
                return false;
            }
            $result = mysqli_fetch_array($check_query,MYSQLI_ASSOC);
            if($result){
                $date1 = strtotime($result["time"]);
                $date2 = strtotime(date("Y-m-d H:i:s"));
                $days =round(($date2 - $date1) / 86400);
                if($update && $days > 0)//是否更新日期
                {
                    $time = date("Y-m-d H:i:s");
                    mysqli_query($this->conn,"update urls set time='$time' where id='$id'");
                }
                else
                {
                    if($days > get_expiry())//超过设定时间，删除
                    {
                        mysqli_query($this->conn,"delete from urls where id='$id'");
                        return false;
                    }
                }
                return $result["data"];
            }
            else{
                return false;
            }
        }
    }
?>
