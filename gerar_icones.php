<?php
// Gera os ícones PNG para o PWA
// Acesse: http://localhost/agenda/gerar_icones.php

$sizes = [96, 144, 192, 512];
$dir = __DIR__ . '/icons/';

foreach ($sizes as $size) {
    $file = $dir . 'icon-' . $size . '.png';
    if (file_exists($file)) continue;

    $img = imagecreatetruecolor($size, $size);
    $bg  = imagecolorallocate($img, 31, 94, 122);   // #1f5e7a
    $fg  = imagecolorallocate($img, 255, 255, 255);

    // Fundo arredondado simulado (círculo)
    imagefilledellipse($img, $size/2, $size/2, $size, $size, $bg);

    // Texto centralizado
    $text = 'A+';
    $font_size = intval($size * 0.28);
    $font = 5; // fonte built-in GD

    // Usar fonte built-in
    $tw = imagefontwidth($font) * strlen($text);
    $th = imagefontheight($font);
    $scale = intval($size / 40);
    if ($scale < 1) $scale = 1;

    imagestring($img, $font, intval($size/2 - $tw*$scale/2), intval($size/2 - $th*$scale/2), $text, $fg);

    imagepng($img, $file);
    imagedestroy($img);
    echo "Criado: icon-{$size}.png<br>";
}
echo "Pronto! Ícones gerados em /icons/";
?>
