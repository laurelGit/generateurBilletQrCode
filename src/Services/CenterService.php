<?php

namespace App\Services;

use App\Entity\Client;

class CenterService {

    public function makeCenter($pathBilletPng, Client $client) {
        // Charge le cachet et la photo afin d'y appliquer le tatouage numérique
        $path = dirname(__DIR__, 2).'/public/assets/';
        $imgpath = $path.'img/';

        $stamp = imagecreatefrompng($pathBilletPng);
        $im = imagecreatefromjpeg($imgpath.'billet.jpg');

        // Définit les marges pour le cachet et récupère la hauteur et la largeur de celui-ci
        $sx = imagesx($stamp);
        $sy = imagesy($stamp);

        // Copie le cachet sur la photo en utilisant les marges et la largeur de la
        // photo originale  afin de calculer la position du cachet 523 55
        imagecopy($im, $stamp, imagesx($im) - $sx - 80, imagesy($im) - $sy - 81, 0, 0, imagesx($stamp), imagesy($stamp));

        // Affichage et libération de la mémoire
        //header('Content-type: image/png');
        //imagepng($im);


        $white = imagecolorallocate($im, 195, 151, 45);

        $txt = $client->getNom()." ".$client->getPrenom();
        $txt1 = $client->getCategorie();
        $font = "C:\Windows\Fonts\arial.ttf"; 
        imagettftext($im, 28, 0, 470, 1555, $white, $font, $txt);
        imagettftext($im, 40, 0, 470, 1740, $white, $font, $txt1);
        $path = dirname(__DIR__, 2).'/public/assets/';
        $namePng = uniqid('', '') . '.png';
        $imgpath = $path.'img_billet/'.$namePng;

        if (file_exists($imgpath)) {
            header("Content-Type: image/png");
            imagepng($im);
        } else {
            header('Content-type: image/png');
            imagepng($im, $imgpath);
            imagedestroy($im);
        }

        return $namePng;
    }
}

?>