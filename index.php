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
        /**
         * @param $path - путь к скачиваемому файлу. Пустая строка считать как рут
         * @return  - контент или сохраняет в локальную папку и возрващает ссылку к файлу.
         */


        $app = new DropboxApp($this->APP_KEY, $this->APP_SECRET, $this->ACCESS_TOKEN);
        $dropbox = new Dropbox($app);
        $file = $dropbox->download($path);
        $contents = $file->getContents();
        @$metadata = $file->getMetadata();
        $save_path = $this->local_path . $path;
        file_put_contents($save_path, $contents);
        return $save_path;
    }

    function makeDir($path, $ftp_connection)
    {
        /**
         * @param $path - путь к папке.
         * @return  - создать папку если ее нет.
         */


        if ($ftp_connection != 0) {
            @$d = is_dir($this->local_path . $path) || mkdir($this->local_path . $path);
            return ftp_mkdir($ftp_connection, "/public_html" . $path);
        } else {
            return is_dir($path) || mkdir($path);
        }
    }

    function get_list_folder($path)
    {
        /**
         * @param $path - путь к папке.
         * парсим ответ дропбокс.
         * @return  - массив файлов и папок.
         */

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
        /**
         * @param $arr - массив данных о папках и файлах. Ответ от дропбокс.
         * если элемент массива папка - создаем папку, если файл отправляем на сервер
         * @return  - .
         */


        foreach ($arr as $item) {
            if ($item['.tag'] == 'folder') {
                echo "It's folder " . $item['name'] . ", Amigo \n";
                self::makeDir($item['path_display'], $ftp_connect);
                sleep(1); //  ddos

                $n_arr = self::get_list_folder($item['path_display'])[0];
//                добираемся до дна рекурсируя
                self::check_files_dropbox($n_arr, $ftp_connect);
            } elseif ($item['.tag'] == 'file') {
                if ($item['is_downloadable']) {
                    $local_file = self::download_file($item['path_display']);
                    self::upload_file_ftp($ftp_connect, $local_file, $item['path_display']);
                    sleep(1);

//                unlink($local_file);
//                todo: проверить без сохранения в локал.
                    echo "Downloaded " . $item['name'] . "\n";
                } else {
                    echo "i can't download this " . $item['name'] . ". Try export?\n";
                }
//            }
            } else {
                echo "Hmm, what is this?\n";
            }
        }
    }

    function start_download($dropbox_path)
    {
        /**
         * @param $dropbox_path - путь в дропбокс, откуда начать.
         * пинок
         * @return  - .
         */

        $root_arr = self::get_list_folder($dropbox_path)[0];
        $conn = ftp_connect($this->FTP_SERVER);
        @$login_result = ftp_login($conn, $this->FTP_USERNAME, $this->FTP_PASS);

        self::check_files_dropbox($root_arr, $conn);

        ftp_close($conn);
    }

}


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






