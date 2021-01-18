<?php
function connection()
{
//    $dsn = 'ld-iobit-com.cylexcs6bned.us-east-1.rds.amazonaws.com';
    $dsn = '127.0.0.1';
    $pass = '';
//    $pass = 'yzfu9CFYcdo8LyyCg7Kd';
    $conn = new mysqli($dsn, 'root', $pass, 'test');
    if (!$conn->error) {
        return $conn;
    } else {
        return false;
    }
}

function log($ip, $id, $action, $date, $line, $heart=0){
    $sql = "INSERT INTO `log`(`ip`, `fbid`, `action`, `date`, `line`, `addheart`) VALUES ('$ip', '$id', '$action', '$date', '$line', '$heart')";
    connection()->query($sql);
}

$conn = connection();

/** */
function str_check($str)
{
    $str = addslashes($str);
    $str = htmlspecialchars($str);
    $str = str_replace(' ', '~~', trim($str));
    return $str;
}

/** */
function set_token($tokenname)
{
    $_SESSION[$tokenname] = md5(microtime(true));
}

/** */
function valid_token($tokenname)
{
    $return = $_POST[$tokenname] == $_SESSION[$tokenname] ? true : false;
    set_token($tokenname);
    return $return;
}

/** */
function checkId($id)
{
    $sql = "select fb_id from `rank` where fb_id='$id'";
    $re = connection()->query($sql)->fetch_assoc()['fb_id'];
    if (!empty($re)) return true;
    else return false;
}

/** */
function shareApi($id, $num)
{
    return connection()->query("update `rank` set heard=heard+$num, shareNum=shareNum+1 where fb_id='$id'");
}

function addHeart($id, $num)
{

    return connection()->query("update `rank` set heard=heard+$num where fb_id='$id'");
}

function checkShare($id)
{

    $num_letter_sql = "SELECT count(*) FROM `letter` where fb_id='$id'";

    $num_share_sql = "SELECT shareNum FROM `rank` where fb_id='$id'";

    $num_letter = connection()->query($num_letter_sql)['count(*)'];

    $num_share = connection()->query($num_share_sql)['shareNum'];

    if ($num_share > $num_letter) return false;

    else return true;
}

function sendbackCheck($sender, $sharer)
{
    $sql = "SELECT COUNT(*) FROM `send_back` WHERE share='$sharer' AND sendback='$sender'";
    $result = connection()->query($sql)->fetch_assoc()['COUNT(*)'];
    if ($result > 0) return false;
    else return true;
}

function fouractionCheck($id, $part)
{
    $sql = "select $part from rank where fb_id='$id'";
    $result = connection()->query($sql)->fetch_assoc()[$part];
    if (empty($result)) return true;
    else return false;
}

function fouractionStatusChange($id, $part)
{
    $sql = "UPDATE `rank` SET $part='YES' WHERE fb_id='$id'";
    $result = connection()->query($sql);
    return $result;
}

function getIP()
{
    if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
        $cip = $_SERVER["HTTP_CLIENT_IP"];
    } elseif (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
        $ip_arr = explode(',',$_SERVER["HTTP_X_FORWARDED_FOR"]);
        $cip=$ip_arr[0];
    } elseif (!empty($_SERVER["REMOTE_ADDR"])) {
        $cip = $_SERVER["REMOTE_ADDR"];
    }else{
        $cip='';
    }
    return $cip;
}

$ip = getIP();
$data = $_POST['data'];
$data = json_decode(base64_decode($data), true);
$type = array_keys($data)[0];
$info = $data[$type];
$date = date("Y-m-d H:i:s");

switch ($type) {

    case 'letter':
        $send_id = str_check($info['fbid']);
        $sender = str_check($info['sender']);
        $recipients = str_check($info['receiver']);
        $words = str_check($info['letter']);
        if (!empty($send_id) && !empty($sender) && !empty($recipients) && !empty($words)) {
            $sql = "INSERT INTO `letter`(`fb_id`, `sender`, `receiver`, `letter`, `date`) values ('$send_id', '$sender', '$recipients', '$words', '$date')";
            $conn->query($sql);
            if ($conn->affected_rows > 0) {
                echo json_encode(['status' => 200, 'data' => '', 'massage' => 'Request success']);
            } else {
                echo json_encode(['status' => 300, 'data' => $conn->error, 'massage' => 'Request error']);
            }
        } else {
            echo json_encode(['status' => 500, 'data' => '', 'massage' => 'Request data error']);
        }
        break;
    case 'share':
        $id = str_check($info['fbid']);
        $fbname = str_check($info['fbname']);

        if (!empty($id) && !empty($fbname)) {

            if (checkId($id)) {

                if (shareApi($id, '2')) {

                    log($ip, $id, 'share', $date, 157, 2);

                    echo json_encode(['status' => 200, 'data' => '', 'massage' => 'Request success']);
                } else {

                    echo json_encode(['status' => 300, 'data' => $conn->error, 'massage' => 'Request error']);
                }
            } else {

                $conn->query("insert into `rank` (fb_id, fb_name) values('$id', '$fbname')");

                if ($conn->affected_rows > 0) {

                    echo json_encode(['status' => 200, 'data' => '', 'massage' => 'Request success']);
                } else {

                    echo json_encode(['status' => 300, 'data' => $conn->error, 'massage' => 'Request error']);
                }
            }
        } else {
            echo json_encode(['status' => 500, 'data' => '', 'massage' => 'Request data error']);
        }
        break;
    case 'rank':
        $sql = "select fb_id as fbid, fb_name as fbname, heard as heart, (select count(distinct(heard)) from `rank` as b where b.heard > a.heard ) + 1 as ranking from `rank` as a order by heard DESC";
        $num = 0;
        $list = [];
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $list[$num] = $row;
                $num++;
            }
        }
        echo json_encode(['status' => 200, 'data' => $list, 'massage' => 'Request success']);
        break;
    case 'userinfo':
        function select_query($sql)
        {
            $num = 0;
            $list = [];
            $result = connection()->query($sql);
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $list[$num] = $row;
                    $num++;
                }
            }
            return $list;
        }

        $id = str_check($info['fbid']);

        if (!empty($id)) {

            if (checkId($id)) {

                /** user's rank info */
                $shareinfo = "select shareNum as receipients, heard as hearts, (select count(distinct(heard)) from `rank` as b where b.heard > a.heard ) + 1 as ransk from `rank` as a where fb_id='$id' order by heard DESC";
                $arr_info = select_query($shareinfo);


                /** user's last letter */
                $letter = "SELECT sender, receiver, letter FROM `letter` WHERE fb_id='$id' ORDER BY date DESC limit 0,1";
                $arr_letter = select_query($letter);


                /** Names of all gifted people */
                $sendlist = "SELECT sendback_name FROM `send_back` where share='$id'";
                $arr_list = select_query($sendlist);

                /** get button status */
                $buttonStatus_sql = "SELECT `followfb`, `followins`, `website`, `timeline` FROM `rank` WHERE fb_id='$id'";

                $result = $conn->query($buttonStatus_sql);

                $rows = $result->fetch_assoc();


                echo json_encode(['status' => 200, 'data' => ['letter' => $arr_letter, 'shareinfo' => $arr_info, 'sendlist' => $arr_list, 'button' => $rows], 'massage' => 'Request success']);
            } else {

                echo json_encode(['status' => 0, 'data' => '', 'massage' => 'User does not exist']);
            }
        } else {

            echo json_encode(['status' => 500, 'data' => '', 'massage' => 'Request data error']);
        }

        break;
    case 'fouraction':
        $id = str_check($info['fbid']);
        $buttonname = str_check($info['buttonname']);

        $type = str_check($info['type']);

        /** Check if id exists and return button status */
        if (checkId($id)) {

            if (in_array($buttonname, array(['followfb', 'website', 'followins', 'timeline']))) {

                if (fouractionCheck($id, $buttonname)) {

                    fouractionStatusChange($id, $buttonname);

                    if ($buttonname == 'timeline') {

                        shareApi($id, '3');

                        log($ip, $id, 'fouraction__timeline', $date, 266, 3);
                    } else {

                        addHeart($sharer, "2");

                        log($ip, $id, 'fouraction__other three', $date, 271, 2);
                    }
                    echo json_encode(['status' => 200, 'data' => '', 'massage' => 'Request success']);
                } else {
                    echo json_encode(['status' => 400, 'data' => '', 'massage' => 'The action has already been performed']);
                }
            } else {
                echo json_encode(['status' => 500, 'data' => '', 'massage' => 'Request data error']);
            }
        } else {
            echo json_encode(['status' => 0, 'data' => '', 'massage' => 'User does not exist']);
        }
        break;
    case 'sendback':
        $sender = str_check($info['sender']);
        $sharer = str_check($info['sharer']);
        $sender_name = str_check($info['sender_name']);

        if ($sender == "4837190343020106") $sender = rand(0, 10000);

        if (!empty($sender) && !empty($sharer)) {

            if (checkId($sharer)) {

                if (sendbackCheck($sender, $sharer)) {

                    $sql = "INSERT INTO `send_back`(`share`, `sendback`, `sendback_name`, `date`) VALUES ('$sharer', '$sender', '$sender_name', '$date')";

                    $conn->query($sql);

                    if ($conn->affected_rows > 0) {

                        echo json_encode(['status' => 200, 'data' => '', 'massage' => 'Request success']);
                    }

                    addHeart($sharer, "1");

                    log($ip, $sharer, 'sendback', $date, 308, 1);
                } else {

                    echo json_encode(['status' => 400, 'data' => '', 'massage' => 'Have been sent back heart']);
                }
            } else {

                echo json_encode(['status' => 0, 'data' => '', 'massage' => 'Shared user does not exist']);
            }
        } else {

            echo json_encode(['status' => 500, 'data' => '', 'massage' => 'Request data error']);
        }

        break;
    default:

        echo json_encode(['status' => 500, 'data' => '', 'massage' => 'Request data(action) error']);
        break;
}
