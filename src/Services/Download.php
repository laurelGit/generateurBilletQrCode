<?php

namespace App\Services;


class Download {

    public function load($path){
        $url = $path;
        $img = 'billetDownloaded.png';
        file_put_contents($img, file_get_contents($url));
        $this->load2($url);
    }

    public function load2($path){
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="billetDownloaded.png"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($path)); //Absolute URL
        ob_clean();
        flush();
        readfile($path); //Absolute URL
        exit();
    }

}

?>