<?php
// echo "hi";
//скрипт который бы вытягивал файлы из под аккаунта dropbox и заливал их по любому ФТП доступу
require('settings.php');

require_once 'vendor/autoload.php';

use Kunnu\Dropbox\Dropbox;
use Kunnu\Dropbox\DropboxApp;

class MyFiles
{
    protected string $ACCESS_TOKEN;
    protected string $APP_KEY;
    protected string $APP_SECRET;
    protected string $FTP_SERVER;
    protected string $FTP_USERNAME;
    protected string $FTP_PASS;

    protected string $local_path;

    function __construct(array $dropbox_auth, array $ftp_auth)
    {
        $this->ACCESS_TOKEN = $dropbox_auth['token'];
        $this->APP_KEY = $dropbox_auth['key'];
        $this->APP_SECRET = $dropbox_auth['secret'];
        $this->FTP_SERVER = $ftp_auth['server'];
        $this->FTP_USERNAME = $ftp_auth['user'];
        $this->FTP_PASS = $ftp_auth['pass'];
        $this->local_path = function () {
            return str_replace("\\", "/", __DIR__ . "/Dropbox");
        };
    }

    function download_file(string $path)
    {
        $app = new DropboxApp($this->APP_KEY, $this->APP_SECRET, $this->ACCESS_TOKEN);
        $dropbox = new Dropbox($app);
        $file = $dropbox->download($path);
        $contents = $file->getContents();
        @$metadata = $file->getMetadata();
//    return $contents;
        $save_path = $GLOBALS['local_path'] . $path;
        file_put_contents($save_path, $contents);
        return $save_path;
    }

    function makeDir($path, $ftp_connection)
    {
        if ($ftp_connection != 0) {
            @$d = is_dir($path) || mkdir($path);
            return ftp_mkdir($ftp_connection, $path);
        } else {
            return is_dir($path) || mkdir($path);
        }
    }

    function get_list_folder($path)
    {
        if ($path == "/") {
            $path = "";
        }
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.dropboxapi.com/2/files/list_folder",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "{\"path\": \"" . $path . "\",\"recursive\": false,\"include_media_info\": false,\"include_deleted\": false,\"include_has_explicit_shared_members\": false,\"include_mounted_folders\": true, \"include_non_downloadable_files\": true}",
            CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer " . $this->ACCESS_TOKEN,
                "Content-Type: application/json",
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
//    $list_files = array_slice(explode('{', $response), 2);
        $list_files = json_decode($response, true)['entries'];
        return array($list_files);
    }

    function upload_file_ftp($connect, $file, $remote_name)
    {
        if (ftp_put($connect, "/public_html" . $remote_name, $file)) {
            echo "$file успешно загружен на сервер\n";
        } else {
            echo "Не удалось загрузить $file на сервер\n";
        }
    }

    function check_files_dropbox($arr, $ftp_connect)
    {
        foreach ($arr as $item) {
            if ($item['.tag'] == 'folder') {
                echo "It's folder " . $item['name'] . ", Amigo \n";

//            todo: создать локальную папку
//            todo: создать ftp папку
                self::makeDir($GLOBALS['local_path'] . "/" . $item['path_display'], $ftp_connect);

                $n_arr = self::get_list_folder($item['path_display'])[0];
                self::check_files_dropbox($n_arr, $ftp_connect); // Todo: Check slash
//            download_folder_zip($download_path . "/" . $item['name'] . "/");
            } elseif ($item['.tag'] == 'file') {
                if ($item['is_downloadable']) {
                    $local_file = self::download_file($item['path_display']);
                    self::upload_file_ftp($ftp_connect, $local_file, $item['path_display']);
//                unlink($local_file);
//                todo: проверить без сохранения в локал.
                    echo "Downloaded " . $item['name'] . "\n";
                } else {
                    echo "i can't download this " . $item['name'] . ". Try export?\n";
//                $d_file = exporting_file($download_from . "/" . $item['name']); // Todo: добавить скачивание
                }
//                todo: удалить если это последний файл
//            if ($item == end($arr) && $level > 0) {
////                    todo что то я тут накуалесил
//                unset($GLOBALS['nested_folders'][$level]);
//                $level -= 1;
//            }
            } else {
                echo "Hmm, what is this?\n";
            }
        }
    }

    function start_download($dropbox_path)
    {
        $root_arr = self::get_list_folder($dropbox_path)[0];
        $conn = ftp_connect($this->FTP_SERVER);
        @$login_result = ftp_login($conn, $this->FTP_USERNAME, $this->FTP_PASS);

        self::check_files_dropbox($root_arr, $conn);

        ftp_close($conn);
    }

}

function exporting_file($file)
{
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://content.dropboxapi.com/2/files/export",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer JDp5Nowy7qAAAAAAAAAAQNemoTLXRDKL0vJX12stvPu6tkh24fxqLjpcSJHz-mrJ",
            "Content-Type: application/octet-stream",
            "Dropbox-API-Arg: {\"path\": \"" . $file . "\"}"
        ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    return $response;
}

//function download_file($path)
//{
//    $app = new DropboxApp($GLOBALS['APP_KEY'], $GLOBALS['APP_SECRET'], $GLOBALS['APP_TOKEN']);
//    $dropbox = new Dropbox($app);
//    $file = $dropbox->download($path);
//    $contents = $file->getContents();
//    $metadata = $file->getMetadata();
////    return $contents;
//    $save_path = $GLOBALS['local_path'] . $path;
//    file_put_contents($save_path, $contents);
//    return $save_path;

//    $curl = curl_init();
//    curl_setopt_array($curl, array(
//        CURLOPT_URL => "https://content.dropboxapi.com/2/files/download",
//        CURLOPT_RETURNTRANSFER => true,
//        CURLOPT_ENCODING => "",
//        CURLOPT_MAXREDIRS => 10,
//        CURLOPT_TIMEOUT => 0,
//        CURLOPT_FOLLOWLOCATION => true,
//        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//        CURLOPT_CUSTOMREQUEST => "POST",
//        CURLOPT_HEADER => 1,
//        CURLOPT_HTTPHEADER => array(
//            "Authorization: Bearer ".$GLOBALS['APP_TOKEN'],
//            "Content-Type: application/octet-stream",
//            "Dropbox-API-Arg: {\"path\": \"" . $file . "\"}"
//        ),
//    ));
//    $response = curl_exec($curl);
//    curl_close($curl);
//    echo $response;
//}

//function download_folder_zip($path)
//{
//    $path = "/" . $path . "/";
//    $curl = curl_init();
//
//    curl_setopt_array($curl, array(
//        CURLOPT_URL => "https://content.dropboxapi.com/2/files/download_zip",
//        CURLOPT_RETURNTRANSFER => true,
//        CURLOPT_ENCODING => "",
//        CURLOPT_MAXREDIRS => 10,
//        CURLOPT_TIMEOUT => 0,
//        CURLOPT_FOLLOWLOCATION => true,
//        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//        CURLOPT_CUSTOMREQUEST => "POST",
//        CURLOPT_HTTPHEADER => array(
//            "Authorization: Bearer " . $GLOBALS['APP_TOKEN'],
//            "Dropbox-API-Arg: {\"path\": \"" . $path . "\"}"
//        ),
//    ));
//
//    $response = curl_exec($curl);
//    curl_close($curl);
//    file_put_contents($GLOBALS['local_path'], $response);
//}


$dropbox_path = "";

# Example my settings...
# Пока не смотрел как подружить php с .env
$DROPBOX_AUTH = array(
    "token" => $APP_TOKEN,
    "key" => $APP_KEY,
    "secret" => $APP_SECRET
);
$FTP_AUTH = array(
    "server" => $FTP_SERVER,
    "user" => $FTP_USERNAME,
    "pass" => $FTP_PASS
);
$files = new MyFiles($DROPBOX_AUTH, $FTP_AUTH);
$files->start_download($dropbox_path);






